<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $role = $request->string('role')->toString();
        $permission = $request->string('permission')->toString();

        $users = User::query()
            ->with(['roles', 'permissions', 'managedEvent:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', function ($query) use ($role) {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $role));
            })
            ->when($permission !== '', function ($query) use ($permission) {
                $query->whereHas('permissions', fn ($permissionQuery) => $permissionQuery->where('name', $permission));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $roles = Role::orderBy('name')->pluck('name');
        $permissions = Permission::orderBy('name')->pluck('name');

        return view('admin.users.index', compact('users', 'roles', 'permissions', 'search', 'role', 'permission'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();
        $events = Event::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.users.create', compact('roles', 'permissions', 'events'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
            'managed_event_id' => ['nullable', 'exists:events,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'managed_event_id' => $validated['managed_event_id'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles(filled($validated['role'] ?? null) ? [$validated['role']] : []);
        $user->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('users')->performedOn($user)->causedBy(auth()->user())->log('User created');

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();
        $events = Event::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.users.edit', compact('user', 'roles', 'permissions', 'events'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$user->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
            'managed_event_id' => ['nullable', 'exists:events,id'],
        ]);

        $data = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'managed_event_id' => $validated['managed_event_id'] ?? null,
        ];

        if (filled($validated['password'] ?? null)) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        $user->syncRoles(filled($validated['role'] ?? null) ? [$validated['role']] : []);
        $user->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('users')->performedOn($user)->causedBy(auth()->user())->log('User updated');

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        activity('users')->causedBy(auth()->user())->log('User deleted: '.$user->username);

        return back()->with('success', 'User deleted successfully.');
    }
}
