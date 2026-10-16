<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/**
 * The one-ticket-at-a-time attendee form is a presentation layer: the server keeps rendering every ticket's fields in
 * the same form (so nothing is lost while navigating, and JS-less browsers still work) and keeps all validation.
 * Click-through behaviour (Back/Next, Enter, pay gate, error reveal) is covered by the browser run documented in docs/checkout-v2.md.
 */
class AttendeeWizardTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.fake.enabled' => true, 'checkout.attendee_wizard' => true]);
        Mail::fake();
        Http::preventStrayRequests();
        $this->event = $this->makeEvent();
        $this->makeMethod('fake', 'fake_card');
    }

    private function begin(array $rows): void
    {
        $cart = rtrim(strtr(base64_encode(json_encode($rows)), '+/', '-_'), '=');
        $this->get(route('front.checkout', ['event' => $this->event->id, 'cart' => $cart]))->assertRedirect(route('front.checkout'));
    }

    private function row(int $qty, array $o = []): array
    {
        return ['ticket_id' => $this->makeTicket($this->event, $o)->id, 'qty' => $qty];
    }

    private function payload(int $people, array $o = []): array
    {
        $att = [];
        for ($i = 1; $i <= $people; $i++) {
            $att[] = ['name' => "Guest $i", 'email' => "guest$i@example.test", 'phone' => "+2010000000$i", 'gender' => 'male', 'social_profile' => "g$i"];
        }

        return array_merge(['full_name' => 'Nour Hassan', 'email' => 'nour@example.test', 'phone' => '+201000000000', 'payment_method' => 'fake_card', 'attendees' => $att], $o);
    }

    public function test_the_wizard_is_on_by_default_and_every_ticket_form_stays_in_the_same_form(): void
    {
        $this->begin([$this->row(4, ['price' => 100])]);

        $html = $this->get(route('front.checkout'))->assertOk()->assertSee('data-attendee-wizard', false)->assertSee('js/tkt-wizard.js', false)->getContent();

        foreach (range(0, 3) as $i) {
            $this->assertStringContainsString('name="attendees['.$i.'][name]"', $html);
            $this->assertStringContainsString('data-attendee="'.$i.'"', $html);
        }
        $form = substr($html, strpos($html, 'id="expressForm"'));
        $form = substr($form, 0, strpos($form, '</form>'));
        $this->assertSame(4, substr_count($form, 'data-attendee="'));     // all four tickets live inside the one form
        $this->assertStringContainsString('Pay now — 400.00 EGP', $html);  // payment area unchanged
        $this->assertStringContainsString('id="promoInput"', $html);
        $this->assertStringContainsString('id="methodGroup"', $html);
    }

    public function test_one_ticket_and_mixed_categories_get_their_labels_for_the_wizard(): void
    {
        $this->begin([$this->row(1, ['name' => 'GA', 'price' => 100])]);
        $this->get(route('front.checkout'))->assertOk()->assertSee('data-ticket-name="GA"', false)->assertSee('data-persons="1"', false);

        $this->flushSession();
        $this->begin([$this->row(2, ['name' => 'GA', 'price' => 100]), $this->row(1, ['name' => 'VIP', 'price' => 300])]);
        $this->get(route('front.checkout'))->assertOk()
            ->assertSee('data-ticket-name="GA"', false)->assertSee('data-ticket-name="VIP"', false)
            ->assertSee('data-attendee="2"', false)->assertDontSee('data-attendee="3"', false);
    }

    public function test_a_couple_pass_is_two_people_of_the_same_ticket_type_with_unchanged_price_and_count(): void
    {
        $this->begin([$this->row(2, ['name' => 'Couple', 'price' => 250, 'is_couple' => true])]);

        $html = $this->get(route('front.checkout'))->assertOk()->assertSee('Pay now — 500.00 EGP')->getContent();

        $this->assertSame(2, substr_count($html, 'data-couple="1"'));           // two person forms
        $this->assertStringContainsString('data-person="1" data-persons="2"', $html);
        $this->assertStringContainsString('data-person="2" data-persons="2"', $html);
        $this->assertStringContainsString('data-ticket-name="Couple"', $html);
        $this->assertStringNotContainsString('data-attendee="2"', $html);

        $this->postJson(route('front.checkout.store'), $this->payload(2))->assertOk();
        $this->assertSame(2, Order::firstOrFail()->items()->count());
        $this->assertEquals(500, Order::firstOrFail()->total_amount);
    }

    public function test_the_flag_off_renders_the_original_stacked_design(): void
    {
        config(['checkout.attendee_wizard' => false]);
        $this->begin([$this->row(3)]);

        $this->get(route('front.checkout'))->assertOk()
            ->assertDontSee('data-attendee-wizard', false)->assertDontSee('js/tkt-wizard.js', false)->assertDontSee('data-wiz-hint', false)
            ->assertSee('name="attendees[2][name]"', false);
    }

    public function test_outside_production_a_query_switches_designs_and_is_remembered_for_comparison(): void
    {
        $this->begin([$this->row(2)]);

        $this->get(route('front.checkout', ['wizard' => 0]))->assertOk()->assertDontSee('data-attendee-wizard', false)->assertSee('data-dev-compare', false);
        $this->get(route('front.checkout'))->assertOk()->assertDontSee('data-attendee-wizard', false);                // remembered
        $this->get(route('front.checkout', ['wizard' => 1]))->assertOk()->assertSee('data-attendee-wizard', false);
    }

    public function test_production_ignores_the_query_override_and_hides_the_compare_links(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['checkout.attendee_wizard' => false]);
        $this->begin([$this->row(2)]);

        $this->get(route('front.checkout', ['wizard' => 1]))->assertOk()
            ->assertDontSee('data-attendee-wizard', false)->assertDontSee('data-dev-compare', false);
    }

    public function test_the_server_still_refuses_an_incomplete_ticket_and_names_the_ticket_with_the_problem(): void
    {
        $this->begin([$this->row(3)]);
        $bad = $this->payload(3);
        $bad['attendees'][1]['email'] = 'a..b@example.com';   // passes a simple browser check, fails the server rule
        $bad['attendees'][2]['gender'] = '';

        $this->postJson(route('front.checkout.store'), $bad)->assertStatus(422)
            ->assertJsonValidationErrors(['attendees.1.email', 'attendees.2.gender'])
            ->assertJsonMissingValidationErrors(['attendees.0.name']);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::count());                  // nothing is created while the data is incomplete

        $missing = $this->payload(3);
        array_pop($missing['attendees']);
        $this->postJson(route('front.checkout.store'), $missing)->assertStatus(422)->assertJsonValidationErrors('attendees');
        $this->assertSame(0, Order::count());
    }

    public function test_the_draft_with_every_ticket_is_restored_after_a_refresh(): void
    {
        $this->begin([$this->row(3)]);
        $bad = $this->payload(3);
        $bad['attendees'][2]['email'] = 'oops';
        $this->postJson(route('front.checkout.store'), $bad)->assertStatus(422);

        $this->get(route('front.checkout'))->assertOk()
            ->assertSee('value="Guest 1"', false)->assertSee('value="Guest 2"', false)->assertSee('value="Guest 3"', false)->assertSee('value="oops"', false);
    }

    public function test_the_script_guards_enter_and_the_pay_gate_without_touching_the_server_flow(): void
    {
        $js = file_get_contents(public_path('js/tkt-wizard.js'));
        $express = file_get_contents(public_path('js/tkt-express.js'));

        $this->assertStringContainsString("e.key !== 'Enter'", $js);               // Enter never submits while filling tickets
        $this->assertStringContainsString('ensureComplete', $js);
        $this->assertStringContainsString('wizardGate', $express);                 // submit is gated before any request is made
        $this->assertStringContainsString('revealErrors', $express);               // server errors open the ticket that has them
        $this->assertStringNotContainsString('fetch(', $js);                       // navigation never calls the server
    }
}
