<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RoleLabel;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $isSuperAdmin = $this->isSuperAdminAccount($request->user());

        $roles = Role::with('permissions')
            ->when(! $isSuperAdmin, fn ($query) => $this->whereNotProtectedRole($query))
            ->latest()
            ->paginate(15);

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::orderBy('name')->get();

        return view('admin.roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $key = $this->uniqueKey($validated['display_name']);
        if (! $this->isSuperAdminAccount($request->user()) && $this->isProtectedRoleName($key)) {
            return back()->withInput()->withErrors(['display_name' => 'This name is not allowed.']);
        }

        $role = Role::create(['name' => $key]);
        $role->forceFill(['display_name' => trim($validated['display_name']), 'description' => $validated['description'] ?? null])->save();
        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('roles')->performedOn($role)->causedBy(auth()->user())->log('Role created');

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    /** The stable key is generated once from the display name (snake_case, unique) and never edited afterwards. */
    private function uniqueKey(string $displayName): string
    {
        $base = Str::slug($displayName, '_') ?: 'role';
        $key = $base;
        for ($i = 2; Role::query()->where('name', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }

    public function edit(Role $role)
    {
        $this->ensureCanManageRole(auth()->user(), $role);

        $permissions = Permission::orderBy('name')->get();

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureCanManageRole($request->user(), $role);

        // The key (`name`) is what the code refers to: it is never changed here, whatever the form sends.
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role->forceFill(['display_name' => trim($validated['display_name']), 'description' => $validated['description'] ?? null])->save();
        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('roles')->performedOn($role)->causedBy(auth()->user())->log('Role updated');

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $this->ensureCanManageRole(auth()->user(), $role);

        if (RoleLabel::isSystem($role)) {
            return back()->with('error', 'System roles are used by the application and cannot be deleted. Edit their permissions instead.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to '.$role->users()->count().' user(s). Move them to another role first.');
        }

        $roleName = $role->name;
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('roles')->causedBy(auth()->user())->log('Role deleted: '.$roleName);

        return back()->with('success', 'Role deleted successfully.');
    }

    private function ensureCanManageRole(?User $actor, Role $role): void
    {
        if ($this->isProtectedRoleName($role->name) && ! $this->isSuperAdminAccount($actor)) {
            abort(403);
        }
    }

    private function isSuperAdminAccount(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->normalizeSuperAdminKey($user->username) === 'superadmin') {
            return true;
        }

        return $user->roles()->where(function ($query) {
            $this->whereProtectedRole($query);
        })->exists();
    }

    private function isProtectedRoleName(?string $roleName): bool
    {
        return $this->normalizeSuperAdminKey($roleName ?? '') === 'superadmin';
    }

    private function normalizeSuperAdminKey(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim($value)));
    }

    private function whereProtectedRole($query)
    {
        return $query->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '_', ''), '-', '')) = ?", ['superadmin']);
    }

    private function whereNotProtectedRole($query)
    {
        return $query->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '_', ''), '-', '')) != ?", ['superadmin']);
    }
}
