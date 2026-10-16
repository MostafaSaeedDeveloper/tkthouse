<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/**
 * The event page's ticket selector is generic: every ticket is independent and comes from the database; nothing is
 * grouped, renamed or classified by its name. Click behaviour (steppers, Show more, selection kept, hand-off to the
 * checkout wizard) is covered by the browser run documented in docs/ticket-selector.md.
 */
class TicketSelectorTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function page(Event $event)
    {
        return $this->get(route('front.events.show', $event))->assertOk();
    }

    private function names(string $html): array
    {
        preg_match_all('/data-ticket="([^"]*)"/', $html, $m);

        return array_map('html_entity_decode', $m[1]);
    }

    public function test_a_single_ticket_renders_one_card_with_a_prominent_price_and_no_extras(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'Only One', 'price' => 1234.5, 'description' => 'Short and sweet']);

        $html = $this->page($event)->assertSee('Only One')->assertSee('1,234.50')->assertSee('Short and sweet')
            ->assertSee('class="tkt-ticket__price"', false)->assertDontSee('data-show-more', false)->assertDontSee('Show more tickets')
            ->getContent();

        $this->assertSame(['Only One'], $this->names($html));
    }

    public function test_three_tickets_keep_their_own_names_and_prices_in_the_existing_price_order(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'Zeta entry', 'price' => 300]);
        $this->makeTicket($event, ['name' => 'Alpha entry', 'price' => 100]);
        $this->makeTicket($event, ['name' => 'Mid entry', 'price' => 200]);

        $html = $this->page($event)->getContent();

        $this->assertSame(['Alpha entry', 'Mid entry', 'Zeta entry'], $this->names($html));   // existing rule: cheapest first (never by name)
        $this->assertStringContainsString('data-price="300.00"', $html);
        $this->assertStringContainsString('data-price="100.00"', $html);
    }

    public function test_no_categories_are_invented_from_ticket_names(): void
    {
        $event = $this->makeEvent();
        foreach (['VIP Wave 2', 'Party Animal', 'Artist Zone', 'Early bird wave 1'] as $name) {
            $this->makeTicket($event, ['name' => $name]);
        }

        $html = $this->page($event)->getContent();

        $this->assertSame(['VIP Wave 2', 'Party Animal', 'Artist Zone', 'Early bird wave 1'], $this->names($html));
        foreach (['tkt-ticket-group', 'data-category', 'data-wave', 'data-tier', 'tkt-ticket--vip'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
        foreach (['tkt-booking.js', 'tkt-v2-pages.css'] as $asset) {
            $src = file_get_contents(public_path(str_contains($asset, '.js') ? "js/$asset" : "css/$asset"));
            $this->assertDoesNotMatchRegularExpression('/vip|party animal|artist zone|\bwave\b/i', $src);   // no name-based rules in code or styles
        }
    }

    public function test_more_than_six_available_tickets_collapse_with_a_show_more_button_and_nothing_is_dropped(): void
    {
        $event = $this->makeEvent();
        foreach (range(1, 17) as $i) {
            $this->makeTicket($event, ['name' => "Ticket type $i", 'price' => 100 + $i]);
        }

        $html = $this->page($event)->assertSee('17 options available')->assertSee('Show more tickets (11)')->getContent();

        $this->assertCount(17, $this->names($html));                                    // every ticket is in the page
        $this->assertSame(11, substr_count($html, 'is-extra'));                          // only the extras are collapsible
        $this->assertStringContainsString('tkt-tickets is-collapsed', $html);
        $this->assertStringContainsString('data-show-more="#ticketList"', $html);
        $this->assertStringContainsString('data-more-label="Show more tickets"', $html);
        $this->assertStringContainsString('data-less-label="Show fewer tickets"', $html);

        $css = file_get_contents(public_path('css/tkt-v2-pages.css'));
        $this->assertStringContainsString('.tkt-tickets.is-collapsed .tkt-ticket.is-extra:not(.is-selected)', $css);   // a selected ticket is never hidden
    }

    public function test_exactly_six_available_tickets_need_no_show_more(): void
    {
        $event = $this->makeEvent();
        foreach (range(1, 6) as $i) {
            $this->makeTicket($event, ['name' => "T$i"]);
        }

        $this->page($event)->assertDontSee('Show more tickets')->assertDontSee('is-collapsed', false);
    }

    public function test_very_long_names_and_descriptions_stay_in_the_page_unchanged_and_are_clamped_by_css(): void
    {
        $event = $this->makeEvent();
        $name = str_repeat('Extraordinarily-long-ticket-name ', 12).'END';
        $desc = str_repeat('A fairly long description sentence. ', 20).'DESC-END';
        $this->makeTicket($event, ['name' => $name, 'description' => $desc, 'price' => 99]);

        $html = $this->page($event)->assertSee('END')->assertSee('DESC-END')->assertSee('data-clamp', false)->getContent();

        $this->assertStringContainsString('title="'.e($name).'"', $html);                // full name always available
        $this->assertStringContainsString('data-clamp-toggle', $html);                   // "Read more" hook
        $css = file_get_contents(public_path('css/tkt-v2-pages.css'));
        $this->assertStringContainsString('-webkit-line-clamp: 3', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere', $css);
    }

    public function test_available_tickets_come_first_and_sold_out_ones_are_compact_rows_below(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'Gone first', 'status' => 'sold_out']);
        $this->makeTicket($event, ['name' => 'Open A']);
        $this->makeTicket($event, ['name' => 'Gone second', 'status' => 'sold_out']);
        $this->makeTicket($event, ['name' => 'Open B']);

        $html = $this->page($event)->assertSee('Sold out')->getContent();

        $this->assertSame(['Open A', 'Open B', 'Gone first', 'Gone second'], $this->names($html));
        $this->assertSame(2, substr_count($html, 'tkt-ticket--compact'));
        // unavailable rows have no quantity control
        $offBlock = substr($html, strpos($html, 'tkt-tickets-off'));
        $this->assertStringNotContainsString('data-step=', $offBlock);
    }

    public function test_many_sold_out_tickets_are_folded_behind_a_toggle(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'Live']);
        foreach (range(1, 6) as $i) {
            $this->makeTicket($event, ['name' => "Gone $i", 'status' => 'sold_out']);
        }

        $this->page($event)->assertSee('Show all sold-out tickets (3 more)')->assertSee('data-show-more="#offList"', false);
    }

    public function test_couple_tickets_keep_their_rule_and_regular_tickets_their_maximum(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'Pair', 'price' => 800, 'is_couple' => true]);
        $this->makeTicket($event, ['name' => 'Single', 'price' => 400, 'max_per_order' => 4]);

        $html = $this->page($event)->assertSee('Couple pass · 2 tickets')->assertSee('Max 4 per order')->getContent();

        $this->assertMatchesRegularExpression('/data-ticket="Pair"[^>]*data-max-qty="2"[^>]*data-is-couple="1"/s', $html);
        $this->assertMatchesRegularExpression('/data-ticket="Single"[^>]*data-max-qty="4"[^>]*data-is-couple="0"/s', $html);
        $js = file_get_contents(public_path('js/tkt-booking.js'));
        $this->assertStringContainsString("var step = isCouple ? 2 : 1;", $js);          // couples move 0 → 2
        $this->assertStringContainsString("qty = Math.max(0, Math.min(maxQty, qty))", $js);
    }

    public function test_hidden_tickets_are_not_listed_and_an_event_without_tickets_says_so(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'Secret', 'status' => 'hidden']);

        $this->page($event)->assertDontSee('Secret')->assertSee('Tickets not available yet');
    }

    public function test_the_order_summary_is_a_separate_block_from_the_ticket_list(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event);

        $html = $this->page($event)->assertSee('tkt-event-aside', false)->assertSee('Your order')->assertSee('id="checkoutBtn"', false)->assertSee('data-express-checkout', false)->getContent();

        $section = substr($html, strpos($html, 'id="tktBooking"'), strpos($html, 'tkt-event-aside') - strpos($html, 'id="tktBooking"'));
        $this->assertStringNotContainsString('id="checkoutBtn"', $section);              // Continue/total live in the summary, not inside the list
        $this->assertStringNotContainsString('id="summaryTotal"', $section);
        $this->assertStringContainsString('data-checkout-base="'.route('front.checkout').'"', $html);
        $this->assertStringContainsString('data-event-id="'.$event->id.'"', $html);
    }

    public function test_an_ended_event_shows_no_selector_or_summary_and_a_sold_out_event_disables_everything(): void
    {
        $ended = $this->makeEvent(['name' => 'Old', 'event_date' => now()->subDays(3)->toDateString()]);
        $this->makeTicket($ended);
        $this->page($ended)->assertSee('This event has ended.')->assertDontSee('tkt-event-aside', false)->assertDontSee('data-step=', false);

        $sold = $this->makeEvent(['name' => 'Sold event', 'status' => 'sold_out']);
        $this->makeTicket($sold, ['name' => 'Was open']);
        $this->page($sold)->assertSee('This event is sold out.')->assertSee('Booking closed')->assertDontSee('data-step=', false);
    }

    public function test_the_hand_off_to_the_checkout_is_unchanged(): void
    {
        $js = file_get_contents(public_path('js/tkt-booking.js'));
        $this->assertStringContainsString("'?event=' + encodeURIComponent(root.dataset.eventId) + '&cart=' + encodeURIComponent(encoded)", $js);
        $this->assertStringContainsString("ticket_id: parseInt(id.replace('ticket-', ''), 10), qty: cart[id].qty", $js);

        $event = $this->makeEvent();
        $ticket = $this->makeTicket($event, ['price' => 100]);
        $cart = rtrim(strtr(base64_encode(json_encode([['ticket_id' => $ticket->id, 'qty' => 2]])), '+/', '-_'), '=');
        $this->get(route('front.checkout', ['event' => $event->id, 'cart' => $cart]))->assertRedirect(route('front.checkout'));
        $this->get(route('front.checkout'))->assertOk()->assertSee('Pay now — 200.00 EGP', false)->assertSee('data-wz', false);   // wizard receives the selection
    }
}
