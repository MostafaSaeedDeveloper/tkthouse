<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $isSuperAdmin = $this->isSuperAdminAccount($currentUser);

        $search = trim((string) $request->string('search'));
        $role = $request->string('role')->toString();
        $permission = $request->string('permission')->toString();

        if (! $isSuperAdmin && $this->isProtectedRoleName($role)) {
            $role = '';
        }

        $users = User::query()
            ->with(['roles', 'permissions', 'managedEvent:id,name'])
            ->when(! $isSuperAdmin, function ($query) {
                $query
                    ->whereRaw("LOWER(username) != ?", ['superadmin'])
                    ->whereDoesntHave('roles', fn ($roleQuery) => $this->whereProtectedRole($roleQuery));
            })
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

        $roles = Role::query()
            ->when(! $isSuperAdmin, fn ($query) => $this->whereNotProtectedRole($query))
            ->orderBy('name')
            ->pluck('name');
        $permissions = Permission::orderBy('name')->pluck('name');

        return view('admin.users.index', compact('users', 'roles', 'permissions', 'search', 'role', 'permission'));
    }

    public function create()
    {
        $isSuperAdmin = $this->isSuperAdminAccount(auth()->user());

        $roles = Role::query()
            ->when(! $isSuperAdmin, fn ($query) => $this->whereNotProtectedRole($query))
            ->orderBy('name')
            ->get();
        $permissions = Permission::orderBy('name')->get();
        $events = Event::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.users.create', compact('roles', 'permissions', 'events'));
    }

    public function store(Request $request)
    {
        $isSuperAdmin = $this->isSuperAdminAccount($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                'unique:users,username',
                Rule::when(! $isSuperAdmin, [function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->normalizeSuperAdminKey((string) $value) === 'superadmin') {
                        $fail('The '.$attribute.' is not allowed.');
                    }
                }]),
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => [
                'nullable',
                'exists:roles,name',
                Rule::when(! $isSuperAdmin, [function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->isProtectedRoleName((string) $value)) {
                        $fail('The selected '.$attribute.' is invalid.');
                    }
                }]),
            ],
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
        $this->ensureCanManageUser(auth()->user(), $user);

        $isSuperAdmin = $this->isSuperAdminAccount(auth()->user());

        $roles = Role::query()
            ->when(! $isSuperAdmin, fn ($query) => $this->whereNotProtectedRole($query))
            ->orderBy('name')
            ->get();
        $permissions = Permission::orderBy('name')->get();
        $events = Event::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.users.edit', compact('user', 'roles', 'permissions', 'events'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureCanManageUser($request->user(), $user);
        $isSuperAdmin = $this->isSuperAdminAccount($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                'unique:users,username,'.$user->id,
                Rule::when(! $isSuperAdmin, [function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->normalizeSuperAdminKey((string) $value) === 'superadmin') {
                        $fail('The '.$attribute.' is not allowed.');
                    }
                }]),
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => [
                'nullable',
                'exists:roles,name',
                Rule::when(! $isSuperAdmin, [function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->isProtectedRoleName((string) $value)) {
                        $fail('The selected '.$attribute.' is invalid.');
                    }
                }]),
            ],
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
        $this->ensureCanManageUser(auth()->user(), $user);

        $user->delete();
        activity('users')->causedBy(auth()->user())->log('User deleted: '.$user->username);

        return back()->with('success', 'User deleted successfully.');
    }

    private function ensureCanManageUser(?User $actor, User $target): void
    {
        if ($this->isSuperAdminAccount($target) && ! $this->isSuperAdminAccount($actor)) {
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
