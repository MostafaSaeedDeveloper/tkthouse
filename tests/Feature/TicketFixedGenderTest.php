<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\User;
use App\Services\Events\EventTicketSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketFixedGenderTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::create([
            'name' => 'Gender Night', 'event_date' => now()->addDays(5)->toDateString(), 'event_time' => '21:00:00',
            'location' => 'Cairo', 'description' => '', 'status' => 'active',
        ]);
    }

    private function begin(User $user, Event $event, array $rows): void
    {
        $cart = rtrim(strtr(base64_encode(json_encode($rows)), '+/', '-_'), '=');
        $this->actingAs($user)->get(route('front.checkout', ['event' => $event->id, 'cart' => $cart]))->assertRedirect(route('front.checkout'));
    }

    public function test_sync_stores_fixed_gender_and_clears_it_for_couple_tickets(): void
    {
        $event = $this->event();
        app(EventTicketSync::class)->sync($event, [
            ['name' => 'Men', 'price' => 100, 'status' => 'active', 'gender' => 'male'],
            ['name' => 'Anyone', 'price' => 100, 'status' => 'active', 'gender' => ''],
            ['name' => 'Pair', 'price' => 200, 'status' => 'active', 'is_couple' => true, 'gender' => 'female'],
        ], []);

        $byName = $event->tickets()->get()->keyBy('name');
        $this->assertSame('male', $byName['Men']->gender);
        $this->assertNull($byName['Anyone']->gender);
        $this->assertNull($byName['Pair']->gender);
    }

    public function test_checkout_shows_no_gender_choice_for_a_fixed_gender_ticket(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $women = EventTicket::create(['event_id' => $event->id, 'name' => 'Ladies', 'price' => 100, 'status' => 'active', 'max_per_order' => 4, 'gender' => 'female']);
        $this->begin($user, $event, [['ticket_id' => $women->id, 'qty' => 1]]);

        $html = $this->actingAs($user)->get(route('front.checkout'))->assertOk()->getContent();
        $this->assertStringContainsString('name="attendees[0][gender]" value="female"', $html);
        $this->assertStringNotContainsString('type="radio" name="attendees[0][gender]"', $html);
    }

    public function test_checkout_still_asks_for_gender_when_ticket_has_none(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $ga = EventTicket::create(['event_id' => $event->id, 'name' => 'GA', 'price' => 100, 'status' => 'active', 'max_per_order' => 4]);
        $this->begin($user, $event, [['ticket_id' => $ga->id, 'qty' => 1]]);

        $html = $this->actingAs($user)->get(route('front.checkout'))->assertOk()->getContent();
        $this->assertStringContainsString('type="radio" name="attendees[0][gender]"', $html);
    }
}
