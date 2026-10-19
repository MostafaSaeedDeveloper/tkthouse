<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Maintenance mode shows the maintenance page to everyone except a signed-in Super Admin, who keeps full access to test the site. */
class MaintenanceBypass
{
    private const ROLES = ['super_admin', 'super admin', 'Super Admin'];

    public static function allows(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && $user->hasAnyRole(self::ROLES);
    }
}
