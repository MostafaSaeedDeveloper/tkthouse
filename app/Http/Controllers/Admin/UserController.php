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

        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true) ? $request->string('status')->toString() : '';
        $orders = $request->boolean('receives_orders');
        $sort = in_array($request->string('sort')->toString(), ['newest', 'name', 'last_login'], true) ? $request->string('sort')->toString() : 'newest';

        // Everyone the current user may see; the filters narrow it, the summary counts always describe this whole set.
        $visible = fn () => User::query()->when(! $isSuperAdmin, function ($query) {
            $query
                ->whereRaw("LOWER(username) != ?", ['superadmin'])
                ->whereDoesntHave('roles', fn ($roleQuery) => $this->whereProtectedRole($roleQuery));
        });

        $stats = [
            'total' => $visible()->count(),
            'active' => $visible()->where('is_active', true)->count(),
            'inactive' => $visible()->where('is_active', false)->count(),
            'receiving' => $visible()->where('can_receive_orders', true)->where('is_active', true)->count(),
        ];

        $users = $visible()
            ->with(['roles', 'permissions', 'managedEvents:id,name'])
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
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($orders, fn ($query) => $query->where('can_receive_orders', true))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'last_login', fn ($query) => $query->orderByRaw('last_login_at IS NULL')->orderByDesc('last_login_at'))
            ->when($sort === 'newest', fn ($query) => $query->latest())
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::query()
            ->when(! $isSuperAdmin, fn ($query) => $this->whereNotProtectedRole($query))
            ->orderBy('name')
            ->pluck('name');
        $permissions = Permission::orderBy('name')->pluck('name');

        return view('admin.users.index', compact('users', 'roles', 'permissions', 'search', 'role', 'permission', 'status', 'orders', 'sort', 'stats'));
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
            'managed_event_ids' => ['nullable', 'array'],
            'managed_event_ids.*' => ['exists:events,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->managedEvents()->sync($validated['managed_event_ids'] ?? []);
        $user->syncRoles(filled($validated['role'] ?? null) ? [$validated['role']] : []);
        $user->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->applyAccountFlags($request, $user->fresh());
        $this->applyPaymentConfiguration($request, $user->fresh());

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
            'managed_event_ids' => ['nullable', 'array'],
            'managed_event_ids.*' => ['exists:events,id'],
        ]);

        $data = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
        ];

        if (filled($validated['password'] ?? null)) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        $user->managedEvents()->sync($validated['managed_event_ids'] ?? []);
        $user->syncRoles(filled($validated['role'] ?? null) ? [$validated['role']] : []);
        $user->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->applyAccountFlags($request, $user->fresh());
        $this->applyPaymentConfiguration($request, $user->fresh());

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

    /**
     * is_active / can_receive_orders are only touched when the form actually sent them (account_flags marker), so an API-style
     * partial update can never disable anyone by omission. Protected rules: the super admin account, the acting user
     * themselves, and customer accounts (no role and no permission) can never be deactivated from here.
     */
    private function applyAccountFlags(Request $request, User $user): void
    {
        if (! $request->boolean('account_flags')) {
            return;
        }

        $isStaff = $user->roles()->exists() || $user->permissions()->exists();
        $wantActive = $request->boolean('is_active');

        if (! $wantActive) {
            abort_if($this->isSuperAdminAccount($user), 422, 'The super admin account cannot be deactivated.');
            abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate your own account.');
            abort_unless($isStaff, 422, 'Only staff accounts can be deactivated here; customer accounts are not affected by staff settings.');
        }

        $user->forceFill([
            'is_active' => $wantActive,
            'can_receive_orders' => $isStaff && $request->boolean('can_receive_orders'),
        ])->save();

        if (! $wantActive) {
            activity('users')->performedOn($user)->causedBy($request->user())->log('User deactivated');
        }
    }

    /**
     * Per-user payment configuration. Only super admin / settings.payment-accounts.manage may change it (the section is not even
     * rendered for anyone else, and a forged request is ignored). The user row stores a reference to an account, never keys.
     * Past orders and payment attempts are unaffected: they stay bound to the account they were opened with.
     */
    private function applyPaymentConfiguration(Request $request, User $user): void
    {
        $actor = $request->user();
        if (! $request->boolean('payment_flags') || ! (\App\Orders\OrderAccess::isSuper($actor) || $actor->can('settings.payment-accounts.manage'))) {
            return;
        }

        $data = $request->validate([
            'payment_routing_mode' => ['required', 'in:default,custom'],
            'payment_account_id' => ['nullable', 'integer', 'exists:integration_accounts,id'],
            'allowed_payment_methods' => ['nullable', 'array'],
            'allowed_payment_methods.*' => ['string', 'exists:payment_methods,code'],
        ]);

        $account = ! empty($data['payment_account_id']) ? \App\Models\IntegrationAccount::query()->find($data['payment_account_id']) : null;
        if ($data['payment_routing_mode'] === 'custom') {
            if (! $account || $account->archived_at !== null || ! in_array($account->provider, ['paymob', 'fawaterak', 'fake'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment_account_id' => 'Choose an active merchant account for custom routing.']);
            }
        }

        $before = [$user->payment_routing_mode, $user->payment_account_id, $user->payment_routing_enabled];
        $user->forceFill([
            'payment_routing_mode' => $data['payment_routing_mode'],
            'payment_account_id' => $data['payment_routing_mode'] === 'custom' ? $account->id : null,
            'allowed_payment_methods' => $data['allowed_payment_methods'] ?? null ?: null,
            'payment_routing_enabled' => $data['payment_routing_mode'] === 'custom' && $request->boolean('payment_routing_enabled'),
        ])->save();

        activity('users')->performedOn($user)->causedBy($actor)->withProperties([
            'from' => $before, 'mode' => $user->payment_routing_mode, 'account' => $account?->name, 'routing_enabled' => $user->payment_routing_enabled,
        ])->log('User payment configuration changed');
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
