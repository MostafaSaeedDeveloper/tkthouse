<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPanelAccess
{
    private const ADMIN_ROLES = [
        'admin',
        'event_manager',
        'ticket_manager',
        'support',
        'scanner',
        'super_admin',
        'super admin',
    ];

    private const ADMIN_PERMISSIONS = [
        'dashboard.view',
        'users.view',
        'users.show',
        'users.create',
        'users.update',
        'users.delete',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'permissions.view',
        'permissions.create',
        'permissions.update',
        'permissions.delete',
        'events.view',
        'events.show',
        'events.create',
        'events.update',
        'events.delete',
        'events.publish',
        'events.mark-sold-out',
        'tickets.view',
        'tickets.manage',
        'scanner.access',
        'fees.manage',
        'event-images.manage',
        'orders.view',
        'orders.manage',
        'orders.deleted.view',
        'attendees.view',
        'attendees.export',
        'activity-logs.view',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $hasAdminRole = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::ADMIN_ROLES);
        $hasAdminPermission = $user && method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission(self::ADMIN_PERMISSIONS);

        if (! $user || (! $hasAdminRole && ! $hasAdminPermission)) {
            abort(403, 'You are not authorized to access the admin dashboard.');
        }

        return $next($request);
    }
}
