<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminEventFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach (['events.create', 'events.update'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create(), fn ($u) => $u->givePermissionTo(['events.create', 'events.update']));
    }

    private function base(array $extra = []): array
    {
        return $extra + ['name' => 'Neon Night', 'event_date' => '2026-12-01', 'event_time' => '20:00', 'location' => 'Cairo', 'status' => 'active', 'requires_booking_approval' => 1];
    }

    public function test_the_untouched_default_rows_do_not_block_saving(): void
    {
        // exactly what the browser sends when the user fills only the basics: one blank ticket row, one blank fee row
        $blankTicket = ['name' => '', 'price' => '', 'status' => 'active', 'color' => '#0d6efd', 'max_per_order' => 10, 'label' => '', 'description' => '', 'is_couple' => 0];
        $blankFee = ['name' => '', 'fee_type' => 'percentage', 'value' => '', 'description' => ''];

        $this->actingAs($this->admin())->post(route('admin.events.store'), $this->base(['tickets' => [$blankTicket], 'fees' => [$blankFee]]))->assertSessionHasNoErrors()->assertRedirect();

        $event = Event::firstOrFail();
        $this->assertSame([0, 0], [$event->tickets()->count(), $event->fees()->count()]);
    }

    public function test_real_rows_are_kept_and_half_filled_rows_still_fail_clearly(): void
    {
        $admin = $this->actingAs($this->admin());
        $ticket = ['name' => 'General', 'price' => '150', 'status' => 'active', 'color' => '#0d6efd', 'max_per_order' => 4, 'is_couple' => 0];
        $blankFee = ['name' => '', 'fee_type' => 'percentage', 'value' => '', 'description' => ''];
        $fee = ['name' => 'Service', 'fee_type' => 'percentage', 'value' => '5', 'description' => ''];

        $admin->post(route('admin.events.store'), $this->base(['tickets' => [$ticket], 'fees' => [$blankFee, $fee]]))->assertSessionHasNoErrors();
        $event = Event::firstOrFail();
        $this->assertSame([1, 1], [$event->tickets()->count(), $event->fees()->count()]);

        // a ticket with a name but no price is a real mistake and must be reported
        $this->post(route('admin.events.store'), $this->base(['name' => 'Other', 'tickets' => [['name' => 'VIP', 'price' => '', 'status' => 'active']]]))->assertSessionHasErrors('tickets.0.price');
        $this->assertSame(1, Event::count());
    }

    public function test_the_form_has_section_navigation_unescaped_titles_and_labels(): void
    {
        $page = $this->actingAs($this->admin())->get(route('admin.events.create'))->assertOk();

        $page->assertSee('Jump to section')->assertSee('id="ev-tickets"', false)->assertSee('Availability &amp; checkout', false)->assertSee('Max per order');
        $this->assertStringNotContainsString('&amp;amp;', $page->getContent());
    }
}
