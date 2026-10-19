<?php

namespace App\Services\Payments\Routing;

use App\Models\CheckoutRouting;
use App\Models\Order;
use App\Models\User;
use App\Orders\OrderAccess;
use App\Orders\OrderAssignmentService;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisional reservations (docs/v2-stage-e-design.md §3). A reservation fixes the agent + merchant account that the customer is
 * shown, but NEVER moves the final round-robin pointer: only a successful order does (consume()). Lock order everywhere is
 * assignment_state row -> reservation row -> order, so concurrent sessions serialise briefly and cannot deadlock.
 */
class CheckoutRoutingService
{
    public const SESSION_KEY = 'checkout.routing_token';
    public const TTL_MINUTES = 30;

    public function __construct(
        private readonly PaymentAccountResolver $resolver,
        private readonly OfferedMethods $offered,
        private readonly OrderAssignmentService $assignment,
    ) {
    }

    public function enabled(): bool
    {
        return PaymentAccountResolver::routingEnabled();
    }

    /** The live reservation of this session, or a fresh one. Idempotent for refresh/back. */
    public function reserve(?string $token, string $sessionId): ?CheckoutRouting
    {
        if (! $this->enabled()) {
            return null;
        }

        return DB::transaction(function () use ($token, $sessionId) {
            $this->lockState();

            if ($token && ($existing = CheckoutRouting::query()->where('token', $token)->live()->lockForUpdate()->first()) && $this->stillValid($existing)) {
                return $existing;
            }
            if ($token) {
                CheckoutRouting::query()->where('token', $token)->whereNull('consumed_order_id')->whereNull('released_at')->update(['released_at' => now()]);
            }

            [$assignee, $source] = $this->pick($token);
            $resolution = $this->resolver->forAssignee($assignee);

            return CheckoutRouting::create([
                'token' => Str::random(40), 'session_hash' => hash('sha256', $sessionId),
                'assignee_id' => $assignee?->id, 'integration_account_id' => $resolution->account?->id,
                'source' => $source, 'snapshot' => $resolution->snapshot(),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        });
    }

    /** Methods when no reservation exists (routing off): the pre-existing list, or the default merchant account's methods. */
    public function methodsWithoutReservation()
    {
        return $this->offered->for($this->resolver->defaultPolicy());
    }

    public function live(?string $token): ?CheckoutRouting
    {
        if (! $token || ! $this->enabled()) {
            return null;
        }
        $r = CheckoutRouting::query()->where('token', $token)->live()->first();

        return $r && $this->stillValid($r) ? $r : null;
    }

    public function resolutionFor(CheckoutRouting $r): Resolution
    {
        $assignee = $r->assignee_id ? User::query()->find($r->assignee_id) : null;
        $base = $this->resolver->forAssignee($assignee);

        // The account that was OFFERED is the account used, as long as it is still usable (checked in stillValid()).
        return new Resolution($r->account, $r->account ? ($base->source === 'agent_account' ? 'agent_account' : 'default_account') : $base->source,
            $r->account === null && $base->blocked, $base->reason, $r->assignee_id, $base->allowedMethods);
    }

    public function methods(CheckoutRouting $r)
    {
        return $this->offered->for($this->resolutionFor($r));
    }

    /**
     * Called inside OrderPlacementService's transaction. Returns what the new order must be created with.
     *
     * @return array{assignee: ?User, account_id: ?int, source: string, reservation: CheckoutRouting}
     * @throws ReservationInvalid
     */
    public function consume(?string $token, ?string $methodCode, bool $needsPayment): array
    {
        $this->lockState();

        $r = $token ? CheckoutRouting::query()->where('token', $token)->lockForUpdate()->first() : null;
        if (! $r) {
            throw ReservationInvalid::because('missing');
        }
        if ($r->consumed_order_id) {
            throw ReservationInvalid::because('consumed');
        }
        if ($r->released_at || $r->expires_at->isPast()) {
            throw ReservationInvalid::because('expired');
        }
        if (! $this->stillValid($r)) {
            throw ReservationInvalid::because('unavailable');
        }
        if ($needsPayment && $methodCode !== null && ! $this->offered->offers($this->resolutionFor($r), $methodCode)) {
            throw ReservationInvalid::because('method_not_offered');
        }

        return [
            'assignee' => $r->assignee_id ? User::query()->find($r->assignee_id) : null,
            'account_id' => $r->integration_account_id,
            'source' => $r->source,
            'reservation' => $r,
        ];
    }

    /** After the order row exists: bind the reservation to it, assign the agent, and advance the FINAL pointer (round robin only). */
    public function finalize(array $decision, Order $order): void
    {
        /** @var CheckoutRouting $r */
        $r = $decision['reservation'];
        $r->forceFill(['consumed_order_id' => $order->id])->save(); // unique: a reservation can only ever feed one order

        if ($decision['assignee']) {
            $this->assignment->assignFromReservation($order, $decision['assignee'], $decision['source']);
            if ($decision['source'] === 'round_robin') {
                DB::table('assignment_state')->update(['last_assigned_user_id' => $decision['assignee']->id, 'updated_at' => now()]);
            }
        }
    }

    public function prune(): int
    {
        return CheckoutRouting::query()->whereNull('consumed_order_id')->where('expires_at', '<', now()->subHour())->delete();
    }

    /** The reservation is only honoured while what the customer was shown is still true. */
    private function stillValid(CheckoutRouting $r): bool
    {
        if ($r->assignee_id) {
            $u = User::query()->find($r->assignee_id);
            if (! $u || ! $this->assignment->isEligible($u)) {
                return false;
            }
        }
        if ($r->integration_account_id) {
            return PaymentAccountResolver::usable($r->account);
        }

        // No account: the answer must still be "legacy" / blocked as shown.
        $now = $this->resolver->forAssignee($r->assignee_id ? User::query()->find($r->assignee_id) : null);

        return $now->account === null;
    }

    /** @return array{0: ?User, 1: string} */
    private function pick(?string $ownToken): array
    {
        if (! OrderAccess::enabled()) {
            return [null, 'default'];
        }

        $mode = (string) SystemSettings::get('order_assignment_mode', 'manual');

        if ($mode === 'default_assignee') {
            $u = $this->assignment->defaultAssignees()->first();

            return $u ? [$u, 'default_assignee'] : [null, 'manual_default'];
        }

        if ($mode !== 'round_robin') {
            return [null, 'manual_default'];
        }

        $eligible = $this->assignment->eligibleUsers();
        if ($eligible->isEmpty()) {
            return [null, 'manual_default'];
        }

        $pointer = (int) (DB::table('assignment_state')->value('last_assigned_user_id') ?? 0);
        $rotation = collect(array_merge(
            $eligible->filter(fn (User $u) => $u->id > $pointer)->values()->all(),
            $eligible->filter(fn (User $u) => $u->id <= $pointer)->values()->all(),
        ));

        // Spread concurrent sessions: take the agent (in rotation order) with the fewest live provisional holds. Holds are
        // provisional and expire; the final pointer still follows completed orders only.
        $holds = CheckoutRouting::query()->live()->when($ownToken, fn ($q) => $q->where('token', '!=', $ownToken))
            ->whereNotNull('assignee_id')->selectRaw('assignee_id, COUNT(*) c')->groupBy('assignee_id')->pluck('c', 'assignee_id');

        $pick = $rotation->reduce(fn (?User $best, User $u) => $best === null || ($holds[$u->id] ?? 0) < ($holds[$best->id] ?? 0) ? $u : $best);

        return [$pick, 'round_robin'];
    }

    private function lockState(): void
    {
        $row = DB::table('assignment_state')->lockForUpdate()->first();
        if (! $row) {
            DB::table('assignment_state')->insert(['last_assigned_user_id' => null, 'updated_at' => now()]);
            DB::table('assignment_state')->lockForUpdate()->first();
        }
    }
}
