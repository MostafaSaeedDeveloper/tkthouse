<?php

namespace App\Orders;

use App\Models\User;
use Spatie\Permission\Models\Permission;

/** Safety checks before Order Assignment may be switched on, so a rollout can't lock staff (or the admin) out. */
class AssignmentReadiness
{
    /** @return array{blockers: list<string>, lockedOut: \Illuminate\Support\Collection} */
    public function check(User $actor, string $mode, ?int $defaultAssigneeId): array
    {
        $blockers = [];

        if (! Permission::query()->where('name', 'orders.view_all')->exists()) {
            $blockers[] = 'Assignment permissions are not installed yet (run the migrations).';
        }
        if (! (OrderAccess::isSuper($actor) || $actor->can('orders.view_all'))) {
            $blockers[] = 'You need the orders.view_all permission (or Super Admin) yourself, otherwise you would lose access to the orders.';
        }
        if ($mode === 'default_assignee') {
            $user = $defaultAssigneeId ? User::query()->find($defaultAssigneeId) : null;
            if (! $user || ! app(OrderAssignmentService::class)->isEligible($user)) {
                $blockers[] = 'Choose an active default assignee who is allowed to receive orders.';
            }
        }

        return ['blockers' => $blockers, 'lockedOut' => $this->lockedOut()];
    }

    /** Active staff who can open Orders today but would see nothing once assignment is on. */
    public function lockedOut()
    {
        return User::query()->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->can('orders.view') && ! OrderAccess::isSuper($u) && ! $u->can('orders.view_all') && ! $u->can('orders.view_assigned'))
            ->values();
    }

    /** Keeps current order viewers' access: grants orders.view_all to exactly those users. */
    public function grantViewAllToLockedOut(User $by): int
    {
        $users = $this->lockedOut();
        foreach ($users as $u) {
            $u->givePermissionTo('orders.view_all');
            activity('settings')->causedBy($by)->performedOn($u)->log('Granted orders.view_all (assignment rollout)');
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $users->count();
    }
}
