<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class ScannerHardeningTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function user(array $perms, array $attrs = []): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create($attrs))->givePermissionTo($perms);
    }

    private function ticket(string $number, ?int $eventId, string $name = 'Alpha Night - GA'): Ticket
    {
        return Ticket::create(['name' => $name, 'price' => 10, 'status' => 'not_checked_in', 'ticket_number' => $number, 'qr_payload' => $number, 'event_id' => $eventId]);
    }

    /* ---- 1: export route is not swallowed by scanners/{user} ---- */

    public function test_export_history_is_a_csv_download_not_a_404(): void
    {
        $admin = $this->user(['scanners.view']);
        ScanLog::create(['action' => 'scanner_login', 'scanner_name' => 'Gate 1', 'scanned_at' => now()]);

        $res = $this->actingAs($admin)->get(route('admin.scanners.export-history'));

        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('scanner_login', $res->streamedContent());
    }

    /* ---- 2: no double check-in ---- */

    public function test_a_second_check_in_is_refused_audited_and_keeps_the_first_time(): void
    {
        $scanner = $this->user(['scanner.access']);
        $ticket = $this->ticket('7770000001', null);

        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $ticket), ['status' => 'checked_in'])->assertRedirect()->assertSessionHas('success');
        $firstAt = $ticket->fresh()->checked_in_at;
        $this->assertNotNull($firstAt);

        $this->travel(5)->minutes();
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $ticket), ['status' => 'checked_in'])
            ->assertRedirect()
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ALREADY CHECKED IN'));

        $fresh = $ticket->fresh();
        $this->assertSame('checked_in', $fresh->status);
        $this->assertTrue($firstAt->equalTo($fresh->checked_in_at), 'the original check-in time is not overwritten');
        $this->assertSame(1, ScanLog::where('ticket_id', $ticket->id)->where('action', 'status_update')->count());
        $this->assertSame(1, ScanLog::where('ticket_id', $ticket->id)->where('action', 'status_update_rejected_duplicate')->count());
    }

    public function test_other_status_changes_still_work_after_a_check_in(): void
    {
        $scanner = $this->user(['scanner.access']);
        $ticket = $this->ticket('7770000002', null);

        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $ticket), ['status' => 'checked_in']);
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $ticket), ['status' => 'not_checked_in'])->assertSessionHas('success');
        $this->assertSame(['not_checked_in', null], [$ticket->fresh()->status, $ticket->fresh()->checked_in_at]);

        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $ticket), ['status' => 'checked_in'])->assertSessionHas('success');
        $this->assertSame('checked_in', $ticket->fresh()->status);
    }

    /* ---- 3: event limits use event_id, not name prefixes ---- */

    public function test_a_scanner_limited_to_one_event_cannot_see_or_update_a_similarly_named_event(): void
    {
        $party = $this->makeEvent(['name' => 'Party']);
        $vol2 = $this->makeEvent(['name' => 'Party - Vol 2']);
        $mine = $this->ticket('7771000001', $party->id, 'Party - GA');
        $other = $this->ticket('7771000002', $vol2->id, 'Party - Vol 2 - VIP');

        $scanner = $this->user(['scanner.access']);
        $scanner->managedEvents()->attach($party->id);
        $scanner = $scanner->fresh();

        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $mine->ticket_number])->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $other->ticket_number])->assertStatus(404);
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $other), ['status' => 'checked_in'])->assertForbidden();
        $this->assertSame('not_checked_in', $other->fresh()->status);
    }

    public function test_event_name_wildcards_do_not_widen_the_scope_for_legacy_tickets(): void
    {
        $event = $this->makeEvent(['name' => '100%']);
        $legacyOwn = $this->ticket('7772000001', null, '100% - GA');
        $legacyOther = $this->ticket('7772000002', null, '100 Nights - GA');

        $scanner = $this->user(['scanner.access']);
        $scanner->managedEvents()->attach($event->id);
        $scanner = $scanner->fresh();

        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $legacyOwn->ticket_number])->assertOk();
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $legacyOther->ticket_number])->assertStatus(404);
    }

    public function test_an_unrestricted_scanner_sees_every_event(): void
    {
        $a = $this->makeEvent(['name' => 'Alpha Night']);
        $t = $this->ticket('7773000001', $a->id);
        $scanner = $this->user(['scanner.access']);

        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $t->ticket_number])->assertOk();
    }

    /* ---- 5: a rejected scanner login leaves no session ---- */

    public function test_valid_credentials_without_scanner_rights_do_not_stay_logged_in(): void
    {
        $user = User::factory()->create(['username' => 'plain_user', 'is_active' => true]);

        $this->post(route('front.scanner.login.submit'), ['username' => 'plain_user', 'password' => 'password'])->assertForbidden();

        $this->assertGuest();
    }

    public function test_a_scanner_account_can_still_log_in(): void
    {
        Permission::findOrCreate('scanner.access', 'web');
        $role = Role::findOrCreate('scanner', 'web');
        $role->givePermissionTo('scanner.access');
        $user = User::factory()->create(['username' => 'gate_1', 'is_active' => true]);
        $user->assignRole('scanner');

        $this->post(route('front.scanner.login.submit'), ['username' => 'gate_1', 'password' => 'password'])->assertRedirect(route('admin.tickets.scanner'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, ScanLog::where('action', 'scanner_login')->count());
    }
}
