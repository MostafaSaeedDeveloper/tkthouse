<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventFee;
use App\Models\EventImage;
use App\Models\EventLineup;
use App\Models\EventTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendV2Test extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'name' => 'Urban Grooves',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '20:00:00',
            'location' => 'District Park, Cairo',
            'cover_image' => 'https://example.com/cover.jpg',
            'description' => '',
            'status' => 'active',
        ], $overrides));
    }

    private function makeTicket(Event $event, array $overrides = []): EventTicket
    {
        return EventTicket::create(array_merge([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 450,
            'status' => 'active',
            'max_per_order' => 6,
        ], $overrides));
    }

    public function test_home_lists_upcoming_and_previous_events_with_real_data(): void
    {
        $upcoming = $this->makeEvent();
        $past = $this->makeEvent(['name' => 'Block Party', 'event_date' => now()->subDays(5)->toDateString()]);

        $response = $this->get(route('front.home'));

        $response->assertOk()
            ->assertSee('Find your next')
            ->assertSee('Urban Grooves')
            ->assertSee('District Park, Cairo')
            ->assertSee('8:00 PM')
            ->assertSee('Get tickets')
            ->assertSee(route('front.events.show', $upcoming), false)
            ->assertSee('Block Party')
            ->assertSee(route('front.events.show', $past), false)
            ->assertSee('Ended');
    }

    public function test_previous_event_cards_have_no_purchase_button(): void
    {
        $this->makeEvent(['name' => 'Old Night', 'event_date' => now()->subDays(3)->toDateString()]);

        $this->get(route('front.home'))
            ->assertOk()
            ->assertSee('Old Night')
            ->assertDontSee('Get tickets')
            ->assertSee('No upcoming events yet');
    }

    public function test_header_and_footer_expose_navigation_and_guest_auth_actions(): void
    {
        $response = $this->get(route('front.home'));

        foreach (['front.home', 'front.about', 'front.events', 'front.contact', 'front.terms', 'front.privacy', 'front.cookie'] as $name) {
            $response->assertSee('href="'.route($name).'"', false);
        }
        $response->assertSee('Log in')->assertSee('Sign up')->assertSee('support@tkthouse.com');
    }

    public function test_header_shows_account_menu_and_logout_for_signed_in_customers(): void
    {
        $user = User::factory()->create(['name' => 'Mona Ali']);

        $this->actingAs($user)->get(route('front.home'))
            ->assertOk()
            ->assertSee('Mona Ali')
            ->assertSee(route('front.account.tickets'), false)
            ->assertSee(route('front.customer.logout'), false)
            ->assertDontSee('data-auth-open="register"', false) // guest-only
            ->assertSee('data-authenticated="1"', false);
    }

    public function test_events_listing_search_filters_and_empty_state(): void
    {
        $this->makeEvent(['name' => 'Sunset Collective', 'location' => 'Rail Yard']);
        $this->makeEvent(['name' => 'Midnight Tour', 'location' => 'Grand Arena']);

        $this->get(route('front.events', ['q' => 'sunset']))
            ->assertOk()
            ->assertSee('Sunset Collective')
            ->assertDontSee('Midnight Tour');

        $this->get(route('front.events', ['q' => 'zzz-nothing']))
            ->assertOk()
            ->assertSee('No upcoming events match your search')
            ->assertSee('No previous events match your search');

        $this->get(route('front.events', ['when' => 'previous']))
            ->assertOk()
            ->assertDontSee('Sunset Collective')
            ->assertDontSee('Upcoming events');
    }

    public function test_events_listing_paginates_upcoming_events(): void
    {
        foreach (range(1, 13) as $i) {
            $this->makeEvent(['name' => 'Event Number '.$i, 'event_date' => now()->addDays($i)->toDateString()]);
        }

        $this->get(route('front.events'))
            ->assertOk()
            ->assertSee('Event Number 12')
            ->assertDontSee('Event Number 13')
            ->assertSee('tkt-pagination', false);

        $this->get(route('front.events', ['page' => 2]))
            ->assertOk()
            ->assertSee('Event Number 13');
    }

    public function test_event_page_renders_tickets_fees_and_checkout_hooks(): void
    {
        $event = $this->makeEvent(['house_rules' => "No glass\nNo re-entry"]);
        $this->makeTicket($event);
        $this->makeTicket($event, ['name' => 'Couple Pass', 'price' => 800, 'is_couple' => true]);
        $this->makeTicket($event, ['name' => 'VIP', 'price' => 950, 'status' => 'sold_out']);
        $this->makeTicket($event, ['name' => 'Secret', 'status' => 'hidden']);
        EventFee::create(['event_id' => $event->id, 'name' => 'Service fee', 'fee_type' => 'percentage', 'value' => 10]);

        $response = $this->get(route('front.events.show', $event));

        $response->assertOk()
            ->assertSee('Urban Grooves')
            ->assertSee('450.00')
            ->assertSee('Max 6 per order')
            ->assertSee('Couple pass · 2 tickets')
            ->assertSee('Sold out')
            ->assertDontSee('Secret')
            ->assertSee('data-id="ticket-'.$event->tickets()->where('name', 'General Admission')->first()->id.'"', false)
            ->assertSee('data-max-qty="6"', false)
            ->assertSee('data-max-qty="2"', false)
            ->assertSee('Service fee')
            ->assertSee('id="checkoutBtn"', false)
            ->assertSee('tkt-checkout-btn', false)
            ->assertSee('data-checkout-base="'.route('front.checkout').'"', false)
            ->assertSee('No glass')
            ->assertSee('js/tkt-booking.js', false)
            // Empty tabs are not rendered
            ->assertDontSee('id="tab-lineup"', false)
            ->assertDontSee('id="tab-gallery"', false)
            ->assertDontSee('id="tab-venue"', false);
    }

    public function test_event_page_shows_only_tabs_that_have_data_and_sanitises_description(): void
    {
        $event = $this->makeEvent([
            'description' => '<p>Big night</p><script>alert(1)</script>',
            'map_embed' => '<iframe src="https://www.google.com/maps/embed?pb=1"></iframe>',
        ]);
        $this->makeTicket($event);
        EventLineup::create(['event_id' => $event->id, 'artist_name' => 'DJ Nova', 'instagram' => '@djnova', 'sort_order' => 1]);
        EventImage::create(['event_id' => $event->id, 'path' => 'https://example.com/photo.jpg']);

        $this->get(route('front.events.show', $event))
            ->assertOk()
            ->assertSee('Big night')
            ->assertDontSee('alert(1)', false)
            ->assertSee('id="tab-lineup"', false)
            ->assertSee('DJ Nova')
            ->assertSee('https://www.instagram.com/djnova', false)
            ->assertSee('id="tab-venue"', false)
            ->assertSee('id="tab-gallery"', false)
            ->assertSee('https://example.com/photo.jpg', false);
    }

    public function test_finished_event_page_has_no_purchase_controls(): void
    {
        $event = $this->makeEvent(['event_date' => now()->subDays(2)->toDateString()]);
        $this->makeTicket($event);

        $this->get(route('front.events.show', $event))
            ->assertOk()
            ->assertSee('Event ended')
            ->assertSee('This event has ended.')
            ->assertDontSee('id="checkoutBtn"', false)
            ->assertDontSee('data-step="1"', false);
    }

    public function test_sold_out_event_disables_selection(): void
    {
        $event = $this->makeEvent(['status' => 'sold_out']);
        $this->makeTicket($event);

        $this->get(route('front.events.show', $event))
            ->assertOk()
            ->assertSee('This event is sold out.')
            ->assertSee('Booking closed')
            ->assertDontSee('data-step="1"', false);
    }

    public function test_event_without_tickets_shows_empty_state(): void
    {
        $event = $this->makeEvent();

        $this->get(route('front.events.show', $event))
            ->assertOk()
            ->assertSee('Tickets not available yet');
    }

    public function test_event_slug_urls_are_unchanged(): void
    {
        $event = $this->makeEvent();

        $this->assertSame(url('/events/urban-grooves'), route('front.events.show', $event));
        $this->get('/events/urban-grooves')->assertOk();
        $this->get('/events/does-not-exist')->assertNotFound();
    }

    public function test_static_pages_use_the_v2_layout_and_keep_their_content(): void
    {
        $expect = [
            'front.about' => 'Who we are',
            'front.contact' => 'Send us a message',
            'front.terms' => 'Terms and Conditions',
            'front.privacy' => 'Information We Collect',
            'front.cookie' => 'Cookie Policy',
        ];

        foreach ($expect as $route => $text) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('data-layout="v2"', false)
                ->assertSee('tkt-header', false)
                ->assertSee($text);
        }
    }

    public function test_contact_form_still_validates_and_submits(): void
    {
        $this->post(route('front.contact.submit'), [])->assertSessionHasErrors(['author', 'phone', 'email', 'comment']);

        $this->post(route('front.contact.submit'), [
            'author' => 'Sara', 'phone' => '+201000000000', 'email' => 'sara@example.com', 'comment' => 'Hello',
        ])->assertRedirect(route('front.contact'))->assertSessionHas('success');
    }

    public function test_auth_pages_use_the_v2_layout_and_keep_their_forms(): void
    {
        $this->get(route('front.customer.login'))
            ->assertOk()
            ->assertSee('data-layout="v2"', false)
            ->assertSee('action="'.route('front.customer.login.store').'"', false)
            ->assertSee('name="login"', false)
            ->assertSee(route('password.request'), false);

        $this->get(route('front.customer.register'))
            ->assertOk()
            ->assertSee('data-layout="v2"', false)
            ->assertSee('data-otp-verify-url="'.route('front.customer.register.verify-otp').'"', false)
            ->assertSee('data-otp-field-wrap', false)
            ->assertSee('data-phone-intl', false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('action="'.route('password.email').'"', false);

        $this->get(route('password.reset', ['token' => 'abc']))
            ->assertOk()
            ->assertSee('name="token" value="abc"', false)
            ->assertSee('action="'.route('password.update').'"', false);
    }

    public function test_auth_modal_keeps_every_hook_used_by_the_auth_scripts(): void
    {
        $html = $this->get(route('front.home'))->assertOk()->getContent();

        foreach ([
            'id="login-register1"', 'data-auth-tab="login"', 'data-auth-tab="register"', 'data-auth-switch="forgot"',
            'data-auth-message="login"', 'data-auth-message="register"', 'data-auth-message="forgot"',
            'data-otp-field-wrap', 'data-redirect-target', 'data-auth-ajax-form',
            'action="'.route('front.customer.login.store').'"', 'action="'.route('front.customer.register.request-otp').'"',
            'data-otp-verify-url="'.route('front.customer.register.verify-otp').'"', 'action="'.route('password.email').'"',
        ] as $hook) {
            $this->assertStringContainsString($hook, $html, "Missing {$hook}");
        }
    }

    public function test_untrusted_map_embeds_are_never_rendered(): void
    {
        $event = $this->makeEvent([
            'map_embed' => '<script>alert(1)</script><iframe src="https://evil.example/x" onload="alert(2)"></iframe>',
            'map_url' => 'javascript:alert(3)',
        ]);

        $this->get(route('front.events.show', $event))
            ->assertOk()
            ->assertDontSee('alert(', false)
            ->assertDontSee('evil.example', false)
            ->assertDontSee('id="tab-venue"', false);
    }

    public function test_approved_map_embeds_are_rebuilt_as_a_sandboxed_iframe(): void
    {
        $event = $this->makeEvent([
            'map_embed' => '<iframe src="https://www.google.com/maps/embed?pb=!1m18" width="600" onload="alert(1)"></iframe>',
        ]);

        $this->get(route('front.events.show', $event))
            ->assertOk()
            ->assertSee('id="tab-venue"', false)
            ->assertSee('src="https://www.google.com/maps/embed?pb=!1m18"', false)
            ->assertSee('sandbox=', false)
            ->assertDontSee('onload', false);
    }

    public function test_flash_messages_are_shown_on_v2_pages(): void
    {
        $this->withSession(['error' => 'Booking is closed for this event.'])
            ->get(route('front.home'))
            ->assertSee('Booking is closed for this event.');
    }
}
