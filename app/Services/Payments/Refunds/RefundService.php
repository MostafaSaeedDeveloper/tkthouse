<?php

namespace App\Services\Payments\Refunds;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\PaymentRefundTicket;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Refund workflow. Money rules:
 *  - the refundable balance of an attempt is  captured - Σ(refunds in requested|processing|unknown|succeeded); it is computed under a row lock on the attempt;
 *  - at most ONE refund per attempt may be in flight; a second request waits for the first to resolve (no over-refund races);
 *  - a request to the provider is claimed by an atomic status update, so it is sent at most once;
 *  - a timeout / 5xx never triggers an automatic re-send: the refund becomes UNKNOWN and is resolved by inquiry or a person;
 *  - success needs positive evidence (provider confirmation) or an explicit, audited human confirmation for manual/unknown cases;
 *  - a legacy paid order (no payment attempt) is refunded manually against the ORDER; no gateway transaction is ever invented;
 *  - ONLY the move to `succeeded` has side effects, and they run in the same database transaction as that move: the tickets the refund
 *    covers are invalidated (never deleted, scan history kept) and the order status becomes refunded / partially_refunded. Either all of it
 *    commits or none of it does, and a repeated confirmation or webhook finds a final refund and does nothing;
 *  - requesting, approving, submitting, processing, failing, rejecting or an unknown outcome NEVER touches tickets or the order.
 */
class RefundService
{
    public function __construct(private readonly RefundGatewayManager $gateways)
    {
    }

    public function capability(PaymentAttempt $attempt): RefundCapability
    {
        if ($attempt->status !== PaymentAttempt::PAID) {
            return new RefundCapability(RefundCapability::NONE, 'Only a successful payment can be refunded.');
        }

        $gateway = $this->gateways->for((string) $attempt->provider);
        if (! $gateway) {
            // e.g. Fawaterak: no refund-creation API is documented, so the refund is carried out outside the system and recorded here.
            return new RefundCapability(RefundCapability::MANUAL, ucfirst((string) $attempt->provider).' has no documented refund API: the refund is done in the provider portal and recorded here (manual / external).');
        }

        $why = $gateway->unsupportedReason($attempt);

        return $why === null
            ? new RefundCapability(RefundCapability::API, null, $gateway->supportsVoid($attempt))
            : new RefundCapability(RefundCapability::MANUAL, $why.' You can still record a manual / external refund.');
    }

    /** Capability of a paid order that has no payment attempt at all (legacy): always manual / external. */
    public function legacyCapability(Order $order): RefundCapability
    {
        if (! in_array($order->status, ['paid', 'partially_refunded'], true)) {
            return new RefundCapability(RefundCapability::NONE, 'Only a paid order can be refunded.');
        }

        return new RefundCapability(RefundCapability::MANUAL, 'This order was paid before online payment records existed, so no provider transaction is linked. The refund is done outside the system and recorded here (manual / external).');
    }

    /** True when the order is paid but has no paid payment attempt (so a refund can only be manual, against the order). */
    public function isLegacyOrder(Order $order): bool
    {
        return ! $order->paymentAttempts()->where('status', PaymentAttempt::PAID)->exists();
    }

    /** @return array{captured: int, held: int, refunded: int, available: int, in_flight: bool} amounts in minor units */
    public function balance(PaymentAttempt $attempt): array
    {
        return $this->balanceFor($attempt, null);
    }

    /** Balance of an attempt, or (attempt null) of a legacy order's own payment (its total). */
    public function balanceFor(?PaymentAttempt $attempt, ?Order $order): array
    {
        $captured = $attempt ? (int) round(((float) $attempt->amount) * 100) : (int) round(((float) $order->total_amount) * 100);
        $rows = PaymentRefund::query()
            ->when($attempt, fn ($q) => $q->where('payment_attempt_id', $attempt->id), fn ($q) => $q->whereNull('payment_attempt_id')->where('order_id', $order->id))
            ->whereIn('status', PaymentRefund::HOLDING)->get(['status', 'amount_minor']);
        $held = (int) $rows->sum('amount_minor');

        return [
            'captured' => $captured, 'held' => $held,
            'refunded' => (int) $rows->where('status', PaymentRefund::SUCCEEDED)->sum('amount_minor'),
            'available' => max(0, $captured - $held),
            'in_flight' => $rows->whereIn('status', PaymentRefund::IN_FLIGHT)->isNotEmpty(),
        ];
    }

    /**
     * Creates the refund record (and sends it when the mode is API and no approval is needed).
     *
     * @param string|null $amount decimal string, null = everything that is still available for type full/void
     * @throws ValidationException
     */
    public function request(PaymentAttempt $attempt, User $by, string $type, ?string $amount, string $reason, string $idempotencyKey, array $options = []): PaymentRefund
    {
        return $this->create($attempt, $attempt->order, $by, $type, $amount, $reason, $idempotencyKey, $options);
    }

    /** Legacy paid order (no payment attempt): a manual refund recorded against the order itself. */
    public function requestForOrder(Order $order, User $by, string $type, ?string $amount, string $reason, string $idempotencyKey, array $options = []): PaymentRefund
    {
        return $this->create(null, $order, $by, $type, $amount, $reason, $idempotencyKey, $options);
    }

    /**
     * @param array{tickets?: array<int>, no_tickets?: bool, external_method?: ?string, internal_notes?: ?string, provider_reference?: ?string, refunded_at?: ?string} $options
     */
    private function create(?PaymentAttempt $attempt, Order $order, User $by, string $type, ?string $amount, string $reason, string $idempotencyKey, array $options): PaymentRefund
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            $fail('A refund reason of at least 5 characters is required.');
        }
        if (! in_array($type, ['full', 'partial', 'void'], true)) {
            $fail('Unknown refund type.');
        }
        if ($attempt === null && $type === 'void') {
            $fail('A void needs a card payment through the provider API.');
        }
        $externalMethod = $options['external_method'] ?? null;
        if ($externalMethod !== null && $externalMethod !== '' && ! array_key_exists($externalMethod, PaymentRefund::EXTERNAL_METHODS)) {
            $fail('Unknown external refund method.');
        }
        $key = hash('sha256', ($attempt ? $attempt->id : 'order'.$order->id).'|'.$idempotencyKey);

        $refund = DB::transaction(function () use ($attempt, $order, $by, $type, $amount, $reason, $key, $options, $externalMethod, $fail) {
            // Lock order: the order first (it serialises ticket selection), then the attempt.
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->withTrashed()->lockForUpdate()->findOrFail($order->id);
            /** @var PaymentAttempt|null $locked */
            $locked = $attempt ? PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id) : null;

            if ($existing = PaymentRefund::query()->where('idempotency_key', $key)->first()) {
                return $existing; // the same submission twice (double click / retry of the form): no second refund
            }
            if ($locked && (int) $locked->order_id !== (int) $lockedOrder->id) {
                $fail('This payment does not belong to the order.');
            }

            $cap = $locked ? $this->capability($locked) : $this->legacyCapability($lockedOrder);
            if ($cap->mode === RefundCapability::NONE) {
                $fail($cap->note);
            }
            $bal = $this->balanceFor($locked, $lockedOrder);
            if ($bal['captured'] <= 0) {
                $fail('Nothing was paid on this order, so there is nothing to refund.');
            }
            if ($bal['in_flight']) {
                $fail('Another refund for this payment is still being processed. Wait until it is confirmed, failed or rejected.');
            }

            [$tickets, $ambiguous] = $this->ticketPlan($lockedOrder, $locked, $bal, $type, $options);

            if ($type === 'void') {
                if (! $cap->canVoid || $bal['held'] > 0) {
                    $fail('A void is only possible for an unrefunded card payment through the provider API, shortly after payment.');
                }
                $minor = $bal['captured'];
            } elseif ($type === 'full') {
                if ($bal['held'] > 0) {
                    $fail('A full refund needs the whole captured amount to be available; use a partial refund for the remainder.');
                }
                $minor = $bal['captured'];
            } else {
                $minor = blank($amount) ? null : PaymentRefund::decimalToMinor((string) $amount);
                if ($minor === null && $tickets['selected'] !== []) {
                    $minor = min($bal['available'], $tickets['cap']); // default: what the selected tickets were worth
                }
                if ($minor === null || $minor <= 0) {
                    $fail('Enter a valid refund amount (up to 2 decimals).');
                }
                if ($tickets['selected'] !== [] && $minor > $tickets['cap']) {
                    $fail('The amount exceeds what the selected ticket(s) were paid ('.PaymentRefund::minorToDecimal($tickets['cap']).' '.($locked->currency ?? 'EGP').'). Select more tickets or lower the amount.');
                }
            }
            if ($minor > $bal['available']) {
                $fail('The refund amount exceeds the refundable balance ('.PaymentRefund::minorToDecimal($bal['available']).' '.($locked->currency ?? 'EGP').').');
            }

            // Four-eyes: when the policy is on, EVERY refund (whoever asks, approver or not) waits for a second person.
            $needsApproval = $this->approvalRequired();

            do {
                $ref = 'REF-'.strtoupper(Str::random(10));
            } while (PaymentRefund::query()->where('internal_reference', $ref)->exists());

            $effects = match (true) {
                $ambiguous => PaymentRefund::EFFECTS_REVIEW,
                $tickets['selected'] === [] => PaymentRefund::EFFECTS_NONE,
                default => PaymentRefund::EFFECTS_PENDING,
            };

            $refund = PaymentRefund::create([
                'internal_reference' => $ref, 'payment_attempt_id' => $locked?->id, 'order_id' => $lockedOrder->id,
                'integration_account_id' => $locked?->integration_account_id, // the ORIGINAL account, whoever owns the order now
                'provider' => $locked ? $locked->provider : 'manual', 'mode' => $cap->isApi() ? 'api' : 'manual', 'type' => $type,
                'status' => PaymentRefund::REQUESTED, 'awaiting_approval' => $needsApproval, 'currency' => $locked ? ($locked->currency ?: 'EGP') : 'EGP',
                'amount_minor' => $minor, 'reason' => mb_substr($reason, 0, 300), 'idempotency_key' => $key,
                'requested_by' => $by->id, 'requested_at' => now(),
                'captured_minor' => $bal['captured'], 'payment_method' => mb_substr((string) ($lockedOrder->payment_method ?? ''), 0, 100) ?: null,
                'external_method' => $cap->isApi() ? null : ($externalMethod ?: null), 'internal_notes' => filled($options['internal_notes'] ?? null) ? mb_substr((string) $options['internal_notes'], 0, 2000) : null,
                'provider_reference' => filled($options['provider_reference'] ?? null) ? mb_substr((string) $options['provider_reference'], 0, 250) : null,
                'refunded_at' => filled($options['refunded_at'] ?? null) ? $this->parseRefundDate((string) $options['refunded_at'], $lockedOrder) : null,
                'effects_status' => $effects,
                'effects_note' => $ambiguous ? 'The order has more than one paid payment: tickets are not cancelled automatically. Select the tickets manually after confirmation.' : null,
                'meta' => ['timeline' => [], 'legacy_payment' => $locked === null, 'previous_order_status' => $lockedOrder->status]
                    + (($locked === null && filled($options['original_reference'] ?? null)) ? ['original_transaction_ref' => mb_substr(trim((string) $options['original_reference']), 0, 120)] : []),
            ]);
            foreach ($tickets['selected'] as $ticketId => $share) {
                PaymentRefundTicket::create(['payment_refund_id' => $refund->id, 'ticket_id' => $ticketId, 'amount_minor' => $share]);
            }
            $this->note($refund, 'requested', $by, $needsApproval ? 'Waiting for approval' : null);

            return $refund;
        });

        if ($refund->wasRecentlyCreated && $refund->mode === 'api' && ! $refund->awaiting_approval) {
            $this->submit($refund);
        }

        return $refund->fresh();
    }

    /** Date the money actually went back: a real date, not in the future, not before the order was paid. */
    private function parseRefundDate(string $value, Order $order): \Illuminate\Support\Carbon
    {
        try {
            $d = \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['refund' => 'The actual refund date is not a valid date.']);
        }
        if ($d->isFuture()) {
            throw ValidationException::withMessages(['refund' => 'The actual refund date cannot be in the future.']);
        }
        $paid = $order->paid_at ?? $order->created_at;
        if ($paid && $d->copy()->endOfDay()->lt($paid)) {
            throw ValidationException::withMessages(['refund' => 'The actual refund date cannot be before the order was paid.']);
        }

        return $d;
    }

    /**
     * Which tickets a refund covers, validated against the order's real tickets and prices.
     *
     * @return array{0: array{selected: array<int,int>, cap: int}, 1: bool} [ticket id => its share of the paid amount (minor units), cap]; ambiguous = cannot be decided safely
     */
    private function ticketPlan(Order $order, ?PaymentAttempt $attempt, array $bal, string $type, array $options): array
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        $all = Ticket::query()->where('order_id', $order->id)->lockForUpdate()->get();
        $claimed = PaymentRefundTicket::query()->whereIn('ticket_id', $all->pluck('id'))
            ->whereHas('refund', fn ($r) => $r->whereIn('status', PaymentRefund::HOLDING))->pluck('ticket_id')->all();
        $eligible = $all->filter(fn (Ticket $t) => $t->status !== 'canceled' && ! in_array($t->id, $claimed, true));
        $shares = $this->shares($order, $all);
        $pick = fn ($tickets) => $tickets->mapWithKeys(fn (Ticket $t) => [$t->id => $shares[$t->id] ?? 0])->all();

        $ambiguous = $order->paymentAttempts()->where('status', PaymentAttempt::PAID)->count() > 1;
        $requested = array_values(array_unique(array_map('intval', (array) ($options['tickets'] ?? []))));

        if ($requested !== [] && ($type === 'partial' || $ambiguous)) {
            $bad = array_diff($requested, $eligible->pluck('id')->all());
            if ($bad) {
                $fail('Some selected tickets cannot be refunded: they do not belong to this order, are already canceled, or are covered by another refund.');
            }
            $selected = $pick($eligible->whereIn('id', $requested));
            $coversAll = count($selected) === $eligible->count();

            return [['selected' => $selected, 'cap' => $coversAll ? $bal['available'] : array_sum($selected)], false];
        }

        if ($type !== 'partial') {
            if ($ambiguous) {
                return [['selected' => [], 'cap' => 0], true];
            }

            return [['selected' => $pick($eligible), 'cap' => $bal['available']], false];
        }

        // Partial refund without a selection: only when the order has no ticket that could be affected, or staff explicitly chose "money only".
        if ($eligible->isNotEmpty() && empty($options['no_tickets'])) {
            $fail('Select the ticket(s) this partial refund covers, or choose "refund money only (do not cancel tickets)".');
        }

        return [['selected' => [], 'cap' => $bal['available']], false];
    }

    /** @return array<int,int> ticket id => minor units (price scaled to what was really paid; rounding always down so shares never exceed the total) */
    private function shares(Order $order, $tickets): array
    {
        $paid = (int) round(((float) $order->total_amount) * 100);
        $base = (int) $tickets->sum(fn (Ticket $t) => (int) round(((float) $t->price) * 100));
        $out = [];
        foreach ($tickets as $t) {
            $out[$t->id] = $base > 0 ? intdiv(((int) round(((float) $t->price) * 100)) * $paid, $base) : 0;
        }

        return $out;
    }

    /** Tickets of an order with their share of the paid amount and whether a new refund may still select them. */
    public function ticketChoices(Order $order): \Illuminate\Support\Collection
    {
        $all = Ticket::query()->where('order_id', $order->id)->orderBy('id')->get();
        $claims = PaymentRefundTicket::query()->with('refund:id,internal_reference,status')->whereIn('ticket_id', $all->pluck('id'))
            ->whereHas('refund', fn ($r) => $r->whereIn('status', PaymentRefund::HOLDING))->get()->keyBy('ticket_id');
        $shares = $this->shares($order, $all);

        return $all->map(function (Ticket $t) use ($claims, $shares) {
            $claim = $claims->get($t->id);
            $reason = match (true) {
                $t->status === 'canceled' => $t->isRefundCanceled() ? 'Refunded and canceled' : 'Already canceled',
                $claim !== null => 'In refund '.$claim->refund->internal_reference,
                default => null,
            };

            return (object) ['ticket' => $t, 'share_minor' => $shares[$t->id] ?? 0, 'selectable' => $reason === null, 'note' => $reason];
        });
    }

    /** The second-person policy. Default ON; read at the moment of every send / confirmation, never cached on the refund. */
    public function approvalRequired(): bool
    {
        return (bool) SystemSettings::get('refund_approval_required', true);
    }

    /**
     * True when this refund may leave the building: either no approval is required, or a DIFFERENT person approved it.
     * Evaluated live, so turning the policy on later also gates refunds that were created while it was off.
     */
    private function approvalSatisfied(PaymentRefund $r): bool
    {
        if (! $this->approvalRequired()) {
            return ! $r->awaiting_approval; // a refund already waiting stays waiting: switching the policy off never auto-approves
        }

        return ! $r->awaiting_approval && $r->approved_by !== null && (int) $r->approved_by !== (int) $r->requested_by;
    }

    /** Puts a not-yet-approved refund back into the approval queue (policy switched on after it was created). */
    private function enforceApprovalFlag(PaymentRefund $refund): void
    {
        DB::transaction(function () use ($refund) {
            $r = PaymentRefund::query()->lockForUpdate()->find($refund->id);
            if ($r && $r->status === PaymentRefund::REQUESTED && ! $this->approvalSatisfied($r) && ! $r->awaiting_approval) {
                $r->awaiting_approval = true;
                $this->note($r, 'approval_required', null, 'The approval policy is on: a second person must approve before anything is sent.');
                $r->save();
            }
        });
    }

    public function approve(PaymentRefund $refund, User $by, ?string $note = null): PaymentRefund
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        if ($this->approvalRequired()) {
            $this->enforceApprovalFlag($refund);
        }
        DB::transaction(function () use ($refund, $by, $note, $fail) {
            $r = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if (! $r->awaiting_approval || $r->status !== PaymentRefund::REQUESTED) {
                $fail('This refund is not waiting for approval.');
            }
            if ((int) $r->requested_by === (int) $by->id) {
                $fail('A refund must be approved by someone other than the requester.');
            }
            if (! $by->can('payments.refunds.approve')) {
                $fail('You are not allowed to approve refunds.');
            }
            $r->forceFill(['awaiting_approval' => false, 'approved_by' => $by->id, 'approved_at' => now()])->save();
            $this->note($r, 'approved', $by, $note);
        });

        $fresh = $refund->fresh();
        if ($fresh->mode === 'api') {
            $this->submit($fresh);
        }

        return $fresh->fresh();
    }

    public function reject(PaymentRefund $refund, User $by, string $note): PaymentRefund
    {
        return $this->transition($refund, $by, [PaymentRefund::REQUESTED], PaymentRefund::REJECTED, 'rejected', $note);
    }

    /** Sends the API request exactly once: the claim is an atomic conditional update. */
    public function submit(PaymentRefund $refund): void
    {
        $required = $this->approvalRequired();
        if ($required) {
            $this->enforceApprovalFlag($refund);
        }
        $claimed = PaymentRefund::query()->whereKey($refund->id)->where('status', PaymentRefund::REQUESTED)->where('awaiting_approval', false)->where('mode', 'api')
            ->when($required, fn ($q) => $q->whereNotNull('approved_by')->whereColumn('approved_by', '!=', 'requested_by'))
            ->update(['status' => PaymentRefund::PROCESSING, 'submitted_at' => now()]);
        if ($claimed !== 1) {
            return; // someone else already sent it (or it is not sendable)
        }

        $refund = $refund->fresh();
        $attempt = $refund->attempt()->with('integrationAccount')->first();
        $gateway = $this->gateways->for((string) $refund->provider);

        try {
            $outcome = $gateway->refund($refund, $attempt);
        } catch (\Throwable $e) {
            // Timeout / connection loss AFTER (possibly) sending: never re-send automatically.
            $this->apply($refund, new RefundOutcome(RefundOutcome::UNKNOWN, null, 'The request may or may not have reached the provider ('.class_basename($e).'). Check the provider before doing anything else.'), 'submit');

            return;
        }
        $this->apply($refund, $outcome, 'submit');
    }

    /** Asks the provider (API refunds in processing/unknown). Only positive evidence changes the state. */
    public function inquire(PaymentRefund $refund): PaymentRefund
    {
        if ($refund->mode !== 'api' || ! in_array($refund->status, [PaymentRefund::PROCESSING, PaymentRefund::UNKNOWN], true)) {
            return $refund;
        }
        $attempt = $refund->attempt()->with('integrationAccount')->first();
        $outcome = $this->gateways->for((string) $refund->provider)->inquire($refund, $attempt);
        $this->apply($refund->fresh(), $outcome, 'inquiry');

        return $refund->fresh();
    }

    /** Manual / external workflow and human resolution of UNKNOWN refunds. */
    public function markSubmitted(PaymentRefund $refund, User $by): PaymentRefund
    {
        return $this->transition($refund, $by, [PaymentRefund::REQUESTED], PaymentRefund::PROCESSING, 'submitted_externally', null, mode: 'manual', blockAwaiting: true);
    }

    public function confirmSucceeded(PaymentRefund $refund, User $by, string $providerReference, string $note, ?string $refundedAt = null, ?string $externalMethod = null): PaymentRefund
    {
        return $this->transition($refund, $by, [PaymentRefund::REQUESTED, PaymentRefund::PROCESSING, PaymentRefund::UNKNOWN], PaymentRefund::SUCCEEDED, 'confirmed_by_staff', $note, providerReference: $providerReference, blockAwaiting: true, refundedAt: $refundedAt, externalMethod: $externalMethod);
    }

    public function confirmFailed(PaymentRefund $refund, User $by, string $note): PaymentRefund
    {
        return $this->transition($refund, $by, [PaymentRefund::REQUESTED, PaymentRefund::PROCESSING, PaymentRefund::UNKNOWN], PaymentRefund::FAILED, 'marked_failed_by_staff', $note, blockAwaiting: true);
    }

    /** Applies a provider outcome to a refund under a row lock. */
    private function apply(PaymentRefund $refund, RefundOutcome $o, string $source): void
    {
        try {
            $this->applyLocked($refund, $o, $source);
        } catch (\Throwable $e) {
            // The provider outcome and the ticket/order side effects commit together. If they could not, NOTHING was changed: the refund stays
            // where it was and the next inquiry (or webhook) applies it again. Never leave a half-applied confirmation.
            report($e);
            PaymentRefund::query()->whereKey($refund->id)->whereNotIn('status', [PaymentRefund::SUCCEEDED, PaymentRefund::FAILED, PaymentRefund::REJECTED])
                ->update(['effects_note' => mb_substr('The provider reported '.$o->status.' but it could not be applied ('.class_basename($e).'). Nothing was changed; check the refund again.', 0, 300)]);
        }
    }

    private function applyLocked(PaymentRefund $refund, RefundOutcome $o, string $source): void
    {
        DB::transaction(function () use ($refund, $o, $source) {
            $r = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($r->isFinal()) {
                return; // a final state is never overwritten by a late/duplicate signal
            }
            $r->last_checked_at = now();
            if ($o->providerRefundId && blank($r->provider_refund_id)) {
                $r->provider_refund_id = mb_substr($o->providerRefundId, 0, 250);
            }

            switch ($o->status) {
                case RefundOutcome::SUCCEEDED:
                    $r->status = PaymentRefund::SUCCEEDED;
                    $r->completed_at = now();
                    $r->refunded_at ??= now();
                    $r->failure_reason = null;
                    $r->effects_note = null;
                    $this->note($r, $o->status, null, $o->message, $source, $o->evidence);
                    $this->finalizeSuccess($r, null);
                    $r->save();

                    return;
                case RefundOutcome::FAILED:
                    $r->status = PaymentRefund::FAILED;
                    $r->completed_at = now();
                    $r->failure_reason = $o->message;
                    break;
                case RefundOutcome::REJECTED:
                    $r->status = PaymentRefund::REJECTED;
                    $r->completed_at = now();
                    $r->failure_reason = $o->message;
                    break;
                case RefundOutcome::UNKNOWN:
                    // From a failed send: unknown. From an inquiry that learned nothing: keep processing/unknown as is.
                    if ($source === 'submit') {
                        $r->status = PaymentRefund::UNKNOWN;
                        $r->failure_reason = $o->message;
                    }
                    break;
                default: // accepted / pending
                    $r->status = $r->status === PaymentRefund::UNKNOWN ? PaymentRefund::PROCESSING : PaymentRefund::PROCESSING;
            }
            $this->note($r, $o->status, null, $o->message, $source, $o->evidence);
            $r->save();
        });
    }

    private function transition(PaymentRefund $refund, User $by, array $from, string $to, string $event, ?string $note, ?string $providerReference = null, ?string $mode = null, bool $requireAwaiting = false, bool $blockAwaiting = false, ?string $refundedAt = null, ?string $externalMethod = null): PaymentRefund
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        if (in_array($event, ['confirmed_by_staff', 'marked_failed_by_staff'], true) && mb_strlen(trim((string) $note)) < 5) {
            $fail('A note describing the evidence is required.');
        }
        if ($event === 'confirmed_by_staff' && blank($providerReference)) {
            $fail('The provider\'s refund reference is required to record a completed refund.');
        }

        if ($blockAwaiting) {
            $this->enforceApprovalFlag($refund);
        }
        try {
            $this->transitionLocked($refund, $by, $from, $to, $event, $note, $providerReference, $mode, $requireAwaiting, $blockAwaiting, $refundedAt, $externalMethod);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['refund' => 'The confirmation could not be completed, so nothing was changed (the refund, the tickets and the order are exactly as before). Please try again; if it keeps failing contact support.']);
        }

        return $refund->fresh();
    }

    private function transitionLocked(PaymentRefund $refund, User $by, array $from, string $to, string $event, ?string $note, ?string $providerReference, ?string $mode, bool $requireAwaiting, bool $blockAwaiting, ?string $refundedAt, ?string $externalMethod): void
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        DB::transaction(function () use ($refund, $by, $from, $to, $event, $note, $providerReference, $mode, $requireAwaiting, $blockAwaiting, $refundedAt, $externalMethod, $fail) {
            $r = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($mode && $r->mode !== $mode) {
                $fail('This action is only for manual / external refunds.');
            }
            if (! in_array($r->status, $from, true)) {
                $fail('This refund is already '.$r->status.'.');
            }
            if ($requireAwaiting && ! $r->awaiting_approval) {
                $fail('Only refunds waiting for approval can be rejected here.');
            }
            if ($blockAwaiting && $r->status === PaymentRefund::REQUESTED && ! $this->approvalSatisfied($r)) {
                $fail('This refund is still waiting for approval.');
            }
            if ($to === PaymentRefund::SUCCEEDED) {
                if (blank($refundedAt)) {
                    $fail('The actual date the refund was made is required to record a completed refund.');
                }
                if ($externalMethod !== null && $externalMethod !== '' && ! array_key_exists($externalMethod, PaymentRefund::EXTERNAL_METHODS)) {
                    $fail('Unknown external refund method.');
                }
            }
            $r->status = $to;
            if (in_array($to, [PaymentRefund::SUCCEEDED, PaymentRefund::FAILED, PaymentRefund::REJECTED], true)) {
                $r->completed_at = now();
            }
            if ($to === PaymentRefund::PROCESSING) {
                $r->submitted_at = now();
                $r->submitted_by = $by->id;
            }
            if ($to === PaymentRefund::SUCCEEDED) {
                $order = Order::query()->withTrashed()->find($r->order_id);
                $r->refunded_at = $this->parseRefundDate((string) $refundedAt, $order);
                $r->confirmed_by = $by->id;
                $r->confirmed_at = now();
                if ($externalMethod) {
                    $r->external_method = $externalMethod;
                }
            }
            if ($providerReference) {
                $r->provider_reference = mb_substr($providerReference, 0, 250);
            }
            if ($to === PaymentRefund::FAILED || $to === PaymentRefund::REJECTED) {
                $r->failure_reason = mb_substr((string) $note, 0, 300);
            }
            $this->note($r, $event, $by, $note, 'staff');
            if ($to === PaymentRefund::SUCCEEDED) {
                $this->finalizeSuccess($r, $by);
            }
            $r->save();
        });
    }

    /**
     * Runs INSIDE the transaction that moves a refund to succeeded (caller holds the row lock). Tickets and order status follow the money.
     * A refund that predates ticket integration (effects_status NULL) is never turned into ticket cancellations on its own: it is flagged
     * for review and a person selects the tickets (resolveTickets()).
     */
    private function finalizeSuccess(PaymentRefund $r, ?User $by): void
    {
        if ($r->effects_status === null) {
            $r->effects_status = PaymentRefund::EFFECTS_REVIEW;
            $r->effects_note = 'This refund was created before ticket integration: select the tickets it covers to cancel them.';
        }
        $this->applyEffects($r, $by);
    }

    /** Idempotent: tickets already invalidated by this refund and an already-applied refund are left alone. */
    private function applyEffects(PaymentRefund $r, ?User $by): void
    {
        if ($r->status !== PaymentRefund::SUCCEEDED || $r->effects_status === PaymentRefund::EFFECTS_APPLIED) {
            return;
        }
        $order = Order::query()->withTrashed()->lockForUpdate()->findOrFail($r->order_id);
        $canceled = [];

        if ($r->effects_status === PaymentRefund::EFFECTS_PENDING) {
            if ($r->isFull()) {
                // every ticket of the order that is still valid and not already tied to this refund
                $have = PaymentRefundTicket::query()->where('payment_refund_id', $r->id)->pluck('ticket_id')->all();
                $all = Ticket::query()->where('order_id', $order->id)->get();
                $shares = $this->shares($order, $all);
                foreach ($all->where('status', '!=', 'canceled')->whereNotIn('id', $have) as $t) {
                    PaymentRefundTicket::create(['payment_refund_id' => $r->id, 'ticket_id' => $t->id, 'amount_minor' => $shares[$t->id] ?? 0]);
                }
            }
            foreach (PaymentRefundTicket::query()->where('payment_refund_id', $r->id)->whereNull('canceled_at')->get() as $link) {
                $ticket = Ticket::query()->lockForUpdate()->find($link->ticket_id);
                if (! $ticket) {
                    continue;
                }
                $wasCheckedIn = $ticket->status === 'checked_in' || $ticket->checked_in_at !== null;
                $previous = $ticket->status;
                $attrs = ['status' => 'canceled', 'cancellation_source' => Ticket::SOURCE_REFUND, 'cancellation_reason' => mb_substr($r->reason, 0, 300), 'canceled_by_refund_id' => $r->id];
                if ($ticket->status !== 'canceled') {
                    $attrs['canceled_at'] = now();
                }
                if ($wasCheckedIn) {
                    $attrs['refund_flag'] = 'refunded_after_check_in'; // the check-in record (checked_in_at, scan logs) is kept untouched
                }
                $ticket->forceFill($attrs)->save();
                $link->forceFill(['canceled_at' => now(), 'was_checked_in' => $wasCheckedIn])->save();
                ScanLog::create([
                    'ticket_id' => $ticket->id, 'event_id' => $ticket->event_id, 'event_name' => $ticket->eventLabel(), 'ticket_number' => $ticket->ticket_number,
                    'action' => 'refund_canceled', 'previous_status' => $previous, 'new_status' => 'canceled', 'payload' => $r->internal_reference.($wasCheckedIn ? ' (was checked in)' : ''),
                    'scanned_by_user_id' => $by?->id, 'scanner_name' => 'Refund '.$r->internal_reference, 'scanned_at' => now(),
                ]);
                $canceled[] = $ticket->ticket_number;
            }
            $r->effects_status = PaymentRefund::EFFECTS_APPLIED;
            $r->effects_applied_at = now();
            $r->effects_note = null;
        } elseif ($r->effects_status === PaymentRefund::EFFECTS_NONE) {
            $r->effects_applied_at = now();
        }

        // Order status follows CONFIRMED money only: refunded when everything paid has been returned, partially_refunded otherwise.
        $refunded = (int) PaymentRefund::query()->where('order_id', $order->id)->where('status', PaymentRefund::SUCCEEDED)->where('id', '!=', $r->id)->sum('amount_minor') + $r->amount_minor;
        $paid = (int) round(((float) $order->total_amount) * 100);
        if (in_array($order->status, ['paid', 'partially_refunded', 'refunded'], true)) {
            $to = ($paid > 0 && $refunded >= $paid) ? 'refunded' : 'partially_refunded';
            if ($to !== $order->status) {
                $from = $order->status;
                $order->forceFill(['status' => $to])->saveQuietly();
                activity('orders')->performedOn($order)->causedBy($by)->withProperties(['from_status' => $from, 'to_status' => $to, 'refund_id' => $r->id, 'refund_reference' => $r->internal_reference])->log('Order status changed');
            }
        }
        $meta = (array) $r->meta;
        $meta['canceled_tickets'] = $canceled;
        $r->meta = $meta;
        $this->note($r, 'effects_applied', $by, $canceled ? count($canceled).' ticket(s) invalidated: '.implode(', ', $canceled) : 'No tickets were affected.', $by ? 'staff' : 'system');
    }

    /**
     * A person selects the tickets for a confirmed refund that could not be matched automatically (more than one paid payment, or the refund
     * predates this feature). Idempotent: once applied it cannot be applied again.
     *
     * @param array<int> $ticketIds
     */
    public function resolveTickets(PaymentRefund $refund, User $by, array $ticketIds): PaymentRefund
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        if (! $by->can('payments.refunds.approve')) {
            $fail('You are not allowed to resolve ticket cancellations.');
        }
        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
        if ($ticketIds === []) {
            $fail('Select at least one ticket.');
        }

        try {
            DB::transaction(function () use ($refund, $by, $ticketIds, $fail) {
                $r = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
                if ($r->status !== PaymentRefund::SUCCEEDED || ! in_array($r->effects_status, [PaymentRefund::EFFECTS_REVIEW, null], true)) {
                    $fail('This refund has no ticket cancellation waiting for review.');
                }
                $order = Order::query()->withTrashed()->lockForUpdate()->findOrFail($r->order_id);
                $all = Ticket::query()->where('order_id', $order->id)->lockForUpdate()->get();
                $others = PaymentRefundTicket::query()->where('payment_refund_id', '!=', $r->id)->whereIn('ticket_id', $ticketIds)
                    ->whereHas('refund', fn ($q) => $q->whereIn('status', PaymentRefund::HOLDING))->exists();
                if ($others || count(array_diff($ticketIds, $all->pluck('id')->all())) > 0) {
                    $fail('Some selected tickets do not belong to this order or are covered by another refund.');
                }
                $shares = $this->shares($order, $all);
                if (! $r->isFull() && array_sum(array_map(fn ($id) => $shares[$id] ?? 0, $ticketIds)) > $r->amount_minor) {
                    $fail('The selected tickets were paid more than this refund returned. Select fewer tickets.');
                }
                foreach ($ticketIds as $id) {
                    PaymentRefundTicket::firstOrCreate(['payment_refund_id' => $r->id, 'ticket_id' => $id], ['amount_minor' => $shares[$id] ?? 0]);
                }
                $r->effects_status = PaymentRefund::EFFECTS_PENDING;
                $this->note($r, 'tickets_selected', $by, count($ticketIds).' ticket(s) selected for cancellation', 'staff');
                $this->applyEffects($r, $by);
                $r->save();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $fail('The ticket cancellation could not be completed, so nothing was changed. Please try again.');
        }

        return $refund->fresh();
    }

    /** Legacy payment without a stored provider id: a person records the original Fawaterak id they found in the dashboard (audited, never invented). */
    public function recordOriginalReference(PaymentRefund $refund, User $by, string $reference): PaymentRefund
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 120 || preg_match('/\s/', $reference)) {
            $fail('Enter the original Fawaterak invoice / transaction id exactly as shown in the Fawaterak dashboard (no spaces, max 120 characters).');
        }
        DB::transaction(function () use ($refund, $by, $reference, $fail) {
            $r = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($r->mode !== 'manual' || $r->isFinal()) {
                $fail('The original reference can only be recorded on an open manual refund.');
            }
            $r->loadMissing('attempt');
            if (filled($r->attempt?->transaction_id) || filled($r->attempt?->provider_reference)) {
                $fail('TKT House already holds the original provider reference of this payment; it cannot be overwritten.');
            }
            $meta = (array) $r->meta;
            $meta['original_transaction_ref'] = $reference;
            $r->meta = $meta;
            $this->note($r, 'original_reference_recorded', $by, 'Original provider id recorded by staff', 'staff');
        });

        return $refund->fresh();
    }

    /** Audit entry for "the operator opened the provider dashboard from the tracking modal". Changes no status; at most one entry per user per 5 minutes. */
    public function logOpenedInProvider(PaymentRefund $refund, User $by): void
    {
        DB::transaction(function () use ($refund, $by) {
            $r = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($r->mode !== 'manual' || $r->isFinal() || $r->awaiting_approval) {
                return;
            }
            $recent = collect($r->timeline())->contains(fn ($t) => ($t['event'] ?? '') === 'opened_in_provider' && ($t['by'] ?? null) === $by->name && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($t['at'])) < 5);
            if (! $recent) {
                $this->note($r, 'opened_in_provider', $by, 'Opened the provider dashboard to carry out the refund (not a completion)', 'staff');
            }
        });
    }

    /** Appends to the visible timeline and the audit log. Never writes secrets. */
    private function note(PaymentRefund $r, string $event, ?User $by, ?string $note = null, string $source = 'system', array $evidence = []): void
    {
        $meta = (array) $r->meta;
        $meta['timeline'][] = array_filter(['at' => now()->toIso8601String(), 'event' => $event, 'source' => $source, 'by' => $by?->name, 'note' => $note ? mb_substr($note, 0, 300) : null, 'evidence' => $evidence ?: null], fn ($v) => $v !== null);
        $r->meta = $meta;
        if ($r->exists) {
            $r->save();
        }
        activity('payments')->performedOn($r)->causedBy($by)->withProperties([
            'event' => $event, 'source' => $source, 'status' => $r->status, 'amount_minor' => $r->amount_minor, 'attempt_id' => $r->payment_attempt_id, 'provider' => $r->provider,
            'reason' => $r->reason, 'note' => $note ? mb_substr($note, 0, 300) : null, 'requested_by' => $r->requested_by, 'requested_at' => $r->requested_at?->toIso8601String(),
            'approved_by' => $r->approved_by, 'approved_at' => $r->approved_at?->toIso8601String(), 'approval_required' => $this->approvalRequired(),
        ])->log('Refund '.str_replace('_', ' ', $event));
    }
}
