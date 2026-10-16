<?php

namespace App\Services\Payments\Refunds;

use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
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
 *  - the refund never changes the order status or the tickets.
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

    /** @return array{captured: int, held: int, refunded: int, available: int, in_flight: bool} amounts in minor units */
    public function balance(PaymentAttempt $attempt): array
    {
        $captured = (int) round(((float) $attempt->amount) * 100);
        $rows = PaymentRefund::query()->where('payment_attempt_id', $attempt->id)->whereIn('status', PaymentRefund::HOLDING)->get(['status', 'amount_minor']);
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
    public function request(PaymentAttempt $attempt, User $by, string $type, ?string $amount, string $reason, string $idempotencyKey): PaymentRefund
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            $fail('A refund reason of at least 5 characters is required.');
        }
        if (! in_array($type, ['full', 'partial', 'void'], true)) {
            $fail('Unknown refund type.');
        }
        $key = hash('sha256', $attempt->id.'|'.$idempotencyKey);

        $refund = DB::transaction(function () use ($attempt, $by, $type, $amount, $reason, $key, $fail) {
            /** @var PaymentAttempt $locked */
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($existing = PaymentRefund::query()->where('idempotency_key', $key)->first()) {
                return $existing; // the same submission twice (double click / retry of the form): no second refund
            }

            $cap = $this->capability($locked);
            if ($cap->mode === RefundCapability::NONE) {
                $fail($cap->note);
            }
            $bal = $this->balance($locked);
            if ($bal['in_flight']) {
                $fail('Another refund for this payment is still being processed. Wait until it is confirmed, failed or rejected.');
            }

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
                $minor = $amount === null ? null : PaymentRefund::decimalToMinor($amount);
                if ($minor === null || $minor <= 0) {
                    $fail('Enter a valid refund amount (up to 2 decimals).');
                }
            }
            if ($minor > $bal['available']) {
                $fail('The refund amount exceeds the refundable balance ('.PaymentRefund::minorToDecimal($bal['available']).' '.$locked->currency.').');
            }

            $needsApproval = (bool) SystemSettings::get('refund_approval_required', false) && ! $by->can('payments.refunds.approve');

            do {
                $ref = 'REF-'.strtoupper(Str::random(10));
            } while (PaymentRefund::query()->where('internal_reference', $ref)->exists());

            $refund = PaymentRefund::create([
                'internal_reference' => $ref, 'payment_attempt_id' => $locked->id, 'order_id' => $locked->order_id,
                'integration_account_id' => $locked->integration_account_id, // the ORIGINAL account, whoever owns the order now
                'provider' => $locked->provider, 'mode' => $cap->isApi() ? 'api' : 'manual', 'type' => $type,
                'status' => PaymentRefund::REQUESTED, 'awaiting_approval' => $needsApproval, 'currency' => $locked->currency ?: 'EGP',
                'amount_minor' => $minor, 'reason' => mb_substr($reason, 0, 300), 'idempotency_key' => $key,
                'requested_by' => $by->id, 'requested_at' => now(),
                'meta' => ['timeline' => []],
            ]);
            $this->note($refund, 'requested', $by, $needsApproval ? 'Waiting for approval' : null);

            return $refund;
        });

        if ($refund->wasRecentlyCreated && $refund->mode === 'api' && ! $refund->awaiting_approval) {
            $this->submit($refund);
        }

        return $refund->fresh();
    }

    public function approve(PaymentRefund $refund, User $by): PaymentRefund
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        DB::transaction(function () use ($refund, $by, $fail) {
            $r = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if (! $r->awaiting_approval || $r->status !== PaymentRefund::REQUESTED) {
                $fail('This refund is not waiting for approval.');
            }
            if ((int) $r->requested_by === (int) $by->id) {
                $fail('A refund must be approved by someone other than the requester.');
            }
            $r->forceFill(['awaiting_approval' => false, 'approved_by' => $by->id])->save();
            $this->note($r, 'approved', $by);
        });

        $fresh = $refund->fresh();
        if ($fresh->mode === 'api') {
            $this->submit($fresh);
        }

        return $fresh->fresh();
    }

    public function reject(PaymentRefund $refund, User $by, string $note): PaymentRefund
    {
        return $this->transition($refund, $by, [PaymentRefund::REQUESTED], PaymentRefund::REJECTED, 'rejected', $note, requireAwaiting: true);
    }

    /** Sends the API request exactly once: the claim is an atomic conditional update. */
    public function submit(PaymentRefund $refund): void
    {
        $claimed = PaymentRefund::query()->whereKey($refund->id)->where('status', PaymentRefund::REQUESTED)->where('awaiting_approval', false)->where('mode', 'api')
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

    public function confirmSucceeded(PaymentRefund $refund, User $by, string $providerReference, string $note): PaymentRefund
    {
        return $this->transition($refund, $by, [PaymentRefund::REQUESTED, PaymentRefund::PROCESSING, PaymentRefund::UNKNOWN], PaymentRefund::SUCCEEDED, 'confirmed_by_staff', $note, providerReference: $providerReference, blockAwaiting: true);
    }

    public function confirmFailed(PaymentRefund $refund, User $by, string $note): PaymentRefund
    {
        return $this->transition($refund, $by, [PaymentRefund::REQUESTED, PaymentRefund::PROCESSING, PaymentRefund::UNKNOWN], PaymentRefund::FAILED, 'marked_failed_by_staff', $note, blockAwaiting: true);
    }

    /** Applies a provider outcome to a refund under a row lock. */
    private function apply(PaymentRefund $refund, RefundOutcome $o, string $source): void
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
                    $r->failure_reason = null;
                    break;
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

    private function transition(PaymentRefund $refund, User $by, array $from, string $to, string $event, ?string $note, ?string $providerReference = null, ?string $mode = null, bool $requireAwaiting = false, bool $blockAwaiting = false): PaymentRefund
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['refund' => $m]);
        if (in_array($event, ['confirmed_by_staff', 'marked_failed_by_staff'], true) && mb_strlen(trim((string) $note)) < 5) {
            $fail('A note describing the evidence is required.');
        }
        if ($event === 'confirmed_by_staff' && blank($providerReference)) {
            $fail('The provider\'s refund reference is required to record a completed refund.');
        }

        DB::transaction(function () use ($refund, $by, $from, $to, $event, $note, $providerReference, $mode, $requireAwaiting, $blockAwaiting, $fail) {
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
            if ($blockAwaiting && $r->awaiting_approval) {
                $fail('This refund is still waiting for approval.');
            }
            $r->status = $to;
            if (in_array($to, [PaymentRefund::SUCCEEDED, PaymentRefund::FAILED, PaymentRefund::REJECTED], true)) {
                $r->completed_at = now();
            }
            if ($to === PaymentRefund::PROCESSING) {
                $r->submitted_at = now();
            }
            if ($providerReference) {
                $r->provider_reference = mb_substr($providerReference, 0, 250);
            }
            if ($to === PaymentRefund::FAILED || $to === PaymentRefund::REJECTED) {
                $r->failure_reason = mb_substr((string) $note, 0, 300);
            }
            $this->note($r, $event, $by, $note, 'staff');
            $r->save();
        });

        return $refund->fresh();
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
        ])->log('Refund '.str_replace('_', ' ', $event));
    }
}
