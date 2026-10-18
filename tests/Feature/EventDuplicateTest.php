<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Event;
use App\Models\EventFee;
use App\Models\EventImage;
use App\Models\EventLineup;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Duplicating an event copies its setup into an off-sale draft and never anything that belongs to the original's history. */
class EventDuplicateTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach (['events.view', 'events.create', 'events.update'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function admin(array $perms = ['events.view', 'events.create']): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->givePermissionTo($perms));
    }

    private function fullEvent(): Event
    {
        $event = $this->makeEvent(['name' => 'Fracture Night', 'cover_image' => 'uploads/events/c.jpg', 'event_banner' => 'uploads/events/banners/b.jpg', 'map_url' => 'https://maps.example/x', 'house_rules' => 'No glass', 'requires_booking_approval' => true]);
        $this->makeTicket($event, ['name' => 'Early', 'price' => 250, 'color' => '#ff0000', 'is_couple' => true, 'sort_order' => 1, 'max_per_order' => 4]);
        $this->makeTicket($event, ['name' => 'VIP', 'price' => 900, 'sort_order' => 2]);
        EventFee::create(['event_id' => $event->id, 'name' => 'Service', 'fee_type' => 'percentage', 'value' => 5]);
        EventLineup::create(['event_id' => $event->id, 'artist_name' => 'DJ A', 'instagram' => '@dja', 'performance_time' => '22:00', 'image' => 'uploads/events/lineup/a.jpg', 'sort_order' => 0]);
        EventImage::create(['event_id' => $event->id, 'path' => 'uploads/events/gallery/g.jpg']);

        return $event;
    }

    public function test_duplicate_creates_an_off_sale_draft_with_the_same_setup(): void
    {
        $source = $this->fullEvent();

        $this->actingAs($this->admin())->post(route('admin.events.duplicate', $source))->assertRedirect();

        $copy = Event::where('id', '!=', $source->id)->firstOrFail();
        $this->assertSame('Fracture Night (Copy)', $copy->name);
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($source->slug, $copy->slug);
        $this->assertSame('fracture-night-copy', $copy->slug);
        $this->assertSame($source->event_date->toDateString(), $copy->event_date->toDateString());
        $this->assertSame('No glass', $copy->house_rules);
        $this->assertTrue($copy->requires_booking_approval);
        $this->assertSame('uploads/events/c.jpg', $copy->cover_image);

        $this->assertSame(['Early', 'VIP'], $copy->tickets->pluck('name')->all());
        $early = $copy->tickets->first();
        $this->assertSame(250.0, (float) $early->price);
        $this->assertSame('#ff0000', $early->color);
        $this->assertTrue($early->is_couple);
        $this->assertSame(4, $early->max_per_order);
        $this->assertSame(1, $copy->fees()->count());
        $this->assertSame('DJ A', $copy->lineups()->firstOrFail()->artist_name);
        $this->assertSame(['uploads/events/gallery/g.jpg'], $copy->images()->pluck('path')->all());

        // The original is untouched and the two events share no rows.
        $this->assertSame('active', $source->fresh()->status);
        $this->assertSame(2, $source->tickets()->count());
        $this->assertEmpty($copy->tickets->pluck('id')->intersect($source->tickets->pluck('id')));
    }

    public function test_duplicate_redirects_to_the_edit_page_of_the_copy(): void
    {
        $source = $this->fullEvent();

        $res = $this->actingAs($this->admin())->post(route('admin.events.duplicate', $source));

        $copy = Event::where('id', '!=', $source->id)->firstOrFail();
        $res->assertRedirect(route('admin.events.edit', $copy))->assertSessionHas('success');
    }

    public function test_history_is_never_copied_and_a_second_copy_gets_a_new_name_and_slug(): void
    {
        $source = $this->fullEvent();
        $buyer = User::factory()->create();
        $this->makePendingOrder($buyer, $source, 2, 250);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.events.duplicate', $source));
        $this->actingAs($admin)->post(route('admin.events.duplicate', $source));

        $names = Event::where('id', '!=', $source->id)->orderBy('id')->pluck('name', 'slug')->all();
        $this->assertSame(['fracture-night-copy' => 'Fracture Night (Copy)', 'fracture-night-copy-2' => 'Fracture Night (Copy 2)'], $names);
        $this->assertSame(1, Order::count(), 'orders stay with the original event');
        $this->assertSame(0, Order::whereIn('id', \DB::table('order_items')->whereIn('event_id', Event::where('id', '!=', $source->id)->pluck('id'))->pluck('order_id'))->count());
    }

    public function test_an_archived_event_can_be_duplicated_and_the_copy_is_a_normal_draft(): void
    {
        $source = $this->fullEvent();
        $source->forceFill(['status' => Event::ARCHIVED, 'archived_at' => now()])->saveQuietly();

        $this->actingAs($this->admin())->post(route('admin.events.duplicate', $source))->assertRedirect();

        $copy = Event::where('id', '!=', $source->id)->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->archived_at);
        $this->assertFalse($copy->isArchived());
    }

    public function test_duplicate_needs_the_create_permission(): void
    {
        $source = $this->fullEvent();

        $this->actingAs($this->admin(['events.view']))->post(route('admin.events.duplicate', $source))->assertForbidden();

        $this->assertSame(1, Event::count());
    }

    public function test_the_events_list_offers_duplicate_only_to_people_who_can_create(): void
    {
        $this->fullEvent();

        $this->actingAs($this->admin())->get(route('admin.events.index'))->assertOk()->assertSee('Duplicate');
        $this->actingAs($this->admin(['events.view']))->get(route('admin.events.index'))->assertOk()->assertDontSee('Duplicate event');
    }
}
