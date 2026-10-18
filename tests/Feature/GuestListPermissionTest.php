<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The guest list has its own permissions and is no longer closed to people restricted by Order Assignment. */
class GuestListPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $perms): User
    {
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create(), fn ($u) => $u->givePermissionTo($perms));
    }

    public function test_the_permissions_exist_and_gate_each_guest_list_screen(): void
    {
        Mail::fake();
        $viewer = $this->user(['guest-list.view']);
        $creator = $this->user(['guest-list.view', 'guest-list.create']);

        $this->actingAs($viewer)->get(route('admin.guest-list.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.guest-list.export'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.guest-list.create', ['event_name' => 'E', 'guest_type' => 'VIP']))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.guest-list.store'), ['event_name' => 'E', 'guest_type' => 'VIP', 'guests' => [['name' => 'G']]])->assertForbidden();

        $this->actingAs($creator)->get(route('admin.guest-list.create', ['event_name' => 'E', 'guest_type' => 'VIP']))->assertOk();
    }

    public function test_the_old_ticket_permissions_alone_no_longer_open_it(): void
    {
        $this->actingAs($this->user(['tickets.view', 'tickets.create']))->get(route('admin.guest-list.index'))->assertForbidden();
    }

    public function test_a_user_restricted_by_order_assignment_can_use_the_guest_list_with_the_new_permission(): void
    {
        SystemSettings::set('order_assignment_enabled', true);
        $agent = $this->user(['orders.view', 'orders.view_assigned', 'guest-list.view', 'guest-list.create']);

        $this->actingAs($agent)->get(route('admin.guest-list.index'))->assertOk();
        $this->actingAs($agent)->get(route('admin.guest-list.create', ['event_name' => 'E', 'guest_type' => 'VIP']))->assertOk();
        $this->actingAs($agent)->get(route('admin.reports.index'))->assertForbidden();   // other global screens stay closed
    }

    public function test_the_menu_shows_guest_list_only_with_the_permission(): void
    {
        $this->actingAs($this->user(['dashboard.view', 'tickets.view']))->get(route('admin.dashboard'))->assertOk()->assertDontSee('Guest list');
        $this->actingAs($this->user(['dashboard.view', 'guest-list.view']))->get(route('admin.dashboard'))->assertOk()->assertSee('Guest list');
    }

    public function test_the_admin_role_and_seeded_ticket_roles_have_them(): void
    {
        $this->createAdmin();
        foreach (['admin', 'event_manager', 'ticket_manager'] as $role) {
            $r = Role::findByName($role, 'web');
            $this->assertTrue($r->hasPermissionTo('guest-list.view'), $role);
            $this->assertTrue($r->hasPermissionTo('guest-list.create'), $role);
        }
        $this->assertFalse(Role::findByName('support', 'web')->hasPermissionTo('guest-list.create'));
    }

    public function test_the_migration_keeps_existing_access_but_not_for_agent_only_roles(): void
    {
        $ticketRole = Role::findOrCreate('Ticket Desk', 'web');
        $agentRole = Role::findOrCreate('Agent Desk', 'web');
        foreach (['tickets.view', 'tickets.create', 'orders.view_assigned'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $ticketRole->givePermissionTo(['tickets.view']);
        $agentRole->givePermissionTo(['tickets.view', 'tickets.create', 'orders.view_assigned']);
        $direct = $this->user(['tickets.create']);

        DB::table('role_has_permissions')->whereIn('permission_id', Permission::whereIn('name', ['guest-list.view', 'guest-list.create'])->pluck('id'))->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', Permission::whereIn('name', ['guest-list.view', 'guest-list.create'])->pluck('id'))->delete();
        (include database_path('migrations/2026_12_10_000003_add_guest_list_permissions.php'))->up();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($ticketRole->fresh()->hasPermissionTo('guest-list.view'));
        $this->assertFalse($ticketRole->fresh()->hasPermissionTo('guest-list.create'), 'only what they had: no tickets.create => no guest-list.create');
        $this->assertFalse($agentRole->fresh()->hasPermissionTo('guest-list.view'), 'agent-only roles never had it under assignment');
        $this->assertTrue($direct->fresh()->hasDirectPermission('guest-list.create'));
    }

    private function guestTicket(?User $by, string $name): \App\Models\Ticket
    {
        $n = 'GL-'.strtoupper(substr(md5($name), 0, 6));

        return \App\Models\Ticket::create(['name' => 'Night - Guest VIP', 'source' => 'guest_list', 'guest_type' => 'Guest VIP', 'status' => 'not_checked_in', 'holder_name' => $name,
            'ticket_number' => $n, 'qr_payload' => $n, 'issued_at' => now(), 'created_by_user_id' => $by?->id]);
    }

    public function test_everyone_sees_only_the_guests_they_added_and_super_admin_sees_all(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $a = $this->user(['guest-list.view', 'tickets.view', 'tickets.delete']);
        $b = $this->user(['guest-list.view', 'tickets.view']);
        $super = $this->user(['guest-list.view', 'tickets.view']);
        $super->assignRole('super_admin');
        $mine = $this->guestTicket($a, 'Alice Guest');
        $theirs = $this->guestTicket($b, 'Bob Guest');
        $legacy = $this->guestTicket(null, 'Legacy Guest');   // no creator on record (added before this rule)

        $this->actingAs($a)->get(route('admin.guest-list.index'))->assertOk()->assertSee('Alice Guest')->assertDontSee('Bob Guest')->assertDontSee('Legacy Guest');
        $this->actingAs($b)->get(route('admin.guest-list.index'))->assertOk()->assertSee('Bob Guest')->assertDontSee('Alice Guest');
        $this->actingAs($super)->get(route('admin.guest-list.index'))->assertOk()->assertSee('Alice Guest')->assertSee('Bob Guest')->assertSee('Legacy Guest');

        $csv = fn (User $u) => $this->actingAs($u)->get(route('admin.guest-list.export'))->streamedContent();
        $this->assertStringContainsString('Alice Guest', $csv($a));
        $this->assertStringNotContainsString('Bob Guest', $csv($a));
        $this->assertStringContainsString('Legacy Guest', $csv($super));

        // other people's guest tickets are not reachable by id either
        $this->actingAs($a)->get(route('admin.tickets.show', $theirs))->assertNotFound();
        $this->actingAs($a)->delete(route('admin.tickets.destroy', $theirs))->assertNotFound();
        $this->assertNotNull(\App\Models\Ticket::find($theirs->id));
        $this->actingAs($a)->get(route('admin.tickets.show', $mine))->assertOk();
        $this->actingAs($super)->get(route('admin.tickets.show', $theirs))->assertOk();
    }

    public function test_new_guest_tickets_record_who_added_them(): void
    {
        Mail::fake();
        $a = $this->user(['guest-list.view', 'guest-list.create']);

        $this->actingAs($a)->post(route('admin.guest-list.store'), ['event_name' => 'E', 'guest_type' => 'VIP', 'guests' => [['name' => 'New Guest']]])->assertRedirect();

        $this->assertSame($a->id, \App\Models\Ticket::where('holder_name', 'New Guest')->value('created_by_user_id'));
        $this->actingAs($a)->get(route('admin.guest-list.index'))->assertOk()->assertSee('New Guest');
    }
}
