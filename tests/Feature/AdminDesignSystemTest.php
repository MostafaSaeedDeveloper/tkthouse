<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** The admin shares one layout, one token file and one component set; screens never ship their own look. */
class AdminDesignSystemTest extends TestCase
{
    use RefreshDatabase, BuildsOrders;

    public function test_every_admin_page_uses_the_one_layout_and_the_design_system_files(): void
    {
        $admin = $this->createAdmin();

        foreach (['admin.dashboard', 'admin.orders.index', 'admin.events.index', 'admin.tickets.index', 'admin.customers.index'] as $route) {
            $html = $this->actingAs($admin)->get(route($route))->assertOk()->getContent();
            foreach (['tokens', 'base', 'layout', 'components'] as $css) {
                $this->assertStringContainsString("admin-ds/css/$css.css", $html, "$route misses $css.css");
            }
            $this->assertStringContainsString('class="a-sidebar"', $html);
            $this->assertStringContainsString('class="a-topbar"', $html);
            $this->assertStringContainsString("classList.contains('dark')", $html);          // Dashmix dark theme can never leak in
            $this->assertStringNotContainsString('admin-v2/', $html);
        }
    }

    public function test_tokens_hold_the_agreed_palette_and_no_other_stylesheet_hardcodes_colours(): void
    {
        $tokens = file_get_contents(public_path('admin-ds/css/tokens.css'));
        foreach (['#FFD200', '#111111', '#F7F8FA', '#FFFFFF', '#171717', '#6B7280', '#E5E7EB'] as $hex) {
            $this->assertStringContainsStringIgnoringCase($hex, $tokens);
        }

        foreach (['base', 'layout', 'components', 'dashboard', 'module-events', 'module-orders', 'module-admin'] as $file) {
            $css = file_get_contents(public_path("admin-ds/css/$file.css"));
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,6}\b/', $css), "$file.css has a hard-coded hex colour");
        }
    }

    public function test_orders_list_collapses_ticket_types_and_keeps_actions_in_a_dropdown(): void
    {
        $admin = $this->createAdmin();
        $event = $this->makeEvent(['name' => 'Long Name Fest']);
        $order = $this->makePendingOrder($admin, $event, 2);
        $order->items()->first()->update(['ticket_name' => 'Long Name Fest - VIP Gold']);
        $order->items()->latest('id')->first()->update(['ticket_name' => 'Long Name Fest - Couple Pass']);

        $html = $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="a-more"', $html);                       // first ticket type + "+1 more"
        $this->assertStringContainsString('+1 more', $html);
        $this->assertStringContainsString('data-a-menu-toggle', $html);                   // row actions live in the dropdown
        $this->assertStringNotContainsString('a-ord-tag', $html);                         // no coloured ticket pills
        $this->assertStringNotContainsString('bg-primary', $html);                        // no default Bootstrap blue
    }

    public function test_pagination_shows_its_summary_once(): void
    {
        $admin = $this->createAdmin();
        for ($i = 0; $i < 20; $i++) {
            $this->makeEvent(['name' => "Event $i"]);
        }

        $html = $this->actingAs($admin)->get(route('admin.events.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Showing '));                           // not repeated by the framework's own text
        $this->assertStringContainsString('class="pagination"', $html);
    }

    public function test_navigation_and_quick_actions_follow_permissions(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $role = Role::firstOrCreate(['name' => 'limited-ui', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::whereIn('name', ['dashboard.view', 'orders.view'])->get());
        $user = User::factory()->create();
        $user->assignRole($role);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.orders.index'), $html);
        $this->assertStringNotContainsString(route('admin.settings.edit'), $html);       // no Settings link
        $this->assertStringNotContainsString(route('admin.promo-codes.index'), $html);   // no Promo Codes link
        $this->assertStringNotContainsString('Create event', $html);                      // no Create event shortcut
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();       // server still enforces it
    }

    public function test_legacy_admin_stylesheets_are_gone(): void
    {
        $this->assertFileDoesNotExist(public_path('admin-v2/admin-v2.css'));
        $this->assertFileDoesNotExist(public_path('admin/assets/css/custom.css'));
        $this->assertFileExists(public_path('admin-ds/admin.js'));
    }
}
