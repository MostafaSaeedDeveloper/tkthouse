<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminUsersIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach (['users.view', 'users.update', 'users.delete'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create(['name' => 'Alice Admin', 'username' => 'alice']), fn ($u) => $u->givePermissionTo(['users.view', 'users.update', 'users.delete']));
    }

    public function test_summary_status_filter_sort_and_self_protection(): void
    {
        $me = $this->admin();
        User::factory()->create(['name' => 'Zed Active', 'username' => 'zed', 'is_active' => true, 'can_receive_orders' => true]);
        User::factory()->create(['name' => 'Bob Blocked', 'username' => 'bob', 'is_active' => false, 'can_receive_orders' => true]);

        $page = $this->actingAs($me)->get(route('admin.users.index'))->assertOk();
        $page->assertSee('Receiving orders')->assertSee('Inactive')->assertSee('You')->assertSee('&#64;zed', false);
        $this->assertSame(['total' => 3, 'active' => 2, 'inactive' => 1, 'receiving' => 1], $page->viewData('stats'), 'inactive users never count as receiving');

        $inactive = $this->actingAs($me)->get(route('admin.users.index', ['status' => 'inactive']))->assertOk();
        $this->assertSame(['Bob Blocked'], $inactive->viewData('users')->pluck('name')->all());
        $this->assertSame(3, $inactive->viewData('stats')['total'], 'summary always describes the whole visible set');

        $pool = $this->actingAs($me)->get(route('admin.users.index', ['receives_orders' => 1]))->viewData('users');
        $this->assertEqualsCanonicalizing(['Zed Active', 'Bob Blocked'], $pool->pluck('name')->all());

        $byName = $this->actingAs($me)->get(route('admin.users.index', ['sort' => 'name']))->viewData('users')->pluck('name')->all();
        $this->assertSame(['Alice Admin', 'Bob Blocked', 'Zed Active'], $byName);

        $this->actingAs($me)->get(route('admin.users.index', ['sort' => 'bogus', 'status' => 'bogus']))->assertOk();

        // no delete button for your own row
        $html = $this->actingAs($me)->get(route('admin.users.index', ['search' => 'alice']))->getContent();
        $this->assertStringNotContainsString('action="'.route('admin.users.destroy', $me).'"', $html);
        $html = $this->actingAs($me)->get(route('admin.users.index', ['search' => 'zed']))->getContent();
        $this->assertStringContainsString('users/', $html);
    }
}
