<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            ->when(! $isSuperAdmin, fn ($query) => $query->where('name', '!=', 'superadmin'))
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
        $isSuperAdmin = $this->isSuperAdminAccount($request->user());

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:roles,name',
                Rule::when(! $isSuperAdmin, ['not_in:superadmin']),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('roles')->performedOn($role)->causedBy(auth()->user())->log('Role created');

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
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
        $isSuperAdmin = $this->isSuperAdminAccount($request->user());

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:roles,name,'.$role->id,
                Rule::when(! $isSuperAdmin, ['not_in:superadmin']),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('roles')->performedOn($role)->causedBy(auth()->user())->log('Role updated');

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $this->ensureCanManageRole(auth()->user(), $role);

        $roleName = $role->name;
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('roles')->causedBy(auth()->user())->log('Role deleted: '.$roleName);

        return back()->with('success', 'Role deleted successfully.');
    }

    private function ensureCanManageRole(?User $actor, Role $role): void
    {
        if ($role->name === 'superadmin' && ! $this->isSuperAdminAccount($actor)) {
            abort(403);
        }
    }

    private function isSuperAdminAccount(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->username === 'superadmin' || $user->hasRole('superadmin');
    }
}
