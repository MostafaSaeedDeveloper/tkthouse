<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/**
 * Compact selector: markup/contract tests. Behaviour that needs a real browser (internal scroll, step-by-step "show more",
 * bottom bar + sheet, selection kept while folding, hand-off to the wizard) is covered by the Playwright run in docs/ticket-selector.md.
 */
class CompactTicketSelectorTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function show(Event $event)
    {
        return $this->get(route('front.events.show', $event))->assertOk();
    }

    private function names(string $html, string $attr = 'data-ticket'): array
    {
        preg_match_all('/'.$attr.'="([^"]*)"/', $html, $m);

        return array_map('html_entity_decode', $m[1]);
    }

    private function many(Event $event, int $n, array $o = []): void
    {
        foreach (range(1, $n) as $i) {
            $this->makeTicket($event, array_merge(['name' => "Type $i", 'price' => 100 + $i], $o));
        }
    }

    public function test_one_ticket_has_no_extras_and_a_disabled_continue(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'Only one', 'price' => 350]);

        $html = $this->show($event)->assertSee('1 ticket type available')->assertSee('Only one')->assertSee('350.00')
            ->assertDontSee('id="soldoutToggle"', false)->getContent();

        $this->assertStringContainsString('id="checkoutBtn"', $html);
        $this->assertMatchesRegularExpression('/id="checkoutBtn"[^>]*aria-disabled="true"/s', $html);   // disabled until something is chosen
        $this->assertMatchesRegularExpression('/class="[^"]*is-disabled[^"]*"[^>]*id="checkoutBtn"|id="checkoutBtn"[^>]*is-disabled/s', $html);
    }

    public function test_five_fifteen_and_thirty_types_all_render_inside_the_one_scroll_area_in_order(): void
    {
        foreach ([5, 15, 30] as $n) {
            $event = $this->makeEvent(['name' => "Event $n"]);
            $this->many($event, $n);

            $html = $this->show($event)->assertSee("$n ticket types available")->getContent();

            $names = $this->names($html);
            $this->assertCount($n, $names);                                              // nothing is dropped
            $this->assertSame("Type 1", $names[0]);
            $this->assertSame("Type $n", end($names));
            $list = substr($html, strpos($html, 'data-ticket-scroll'), strpos($html, 'data-more') - strpos($html, 'data-ticket-scroll'));
            $this->assertSame($n, substr_count($list, 'class="tkt-ticket"'));            // all inside the scroll container
            $this->assertStringContainsString('data-fold-initial="5"', $html);     // 4–5 shown by default
            $this->assertStringContainsString('data-fold-step="5"', $html);
            $this->assertStringContainsString('data-more', $html);
            $this->assertStringContainsString('data-less', $html);                 // Show less
        }
    }

    public function test_sold_out_tickets_live_in_a_closed_accordion_with_tiny_unselectable_rows(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'Open A']);
        $this->makeTicket($event, ['name' => 'Gone 0', 'status' => 'sold_out']);
        $this->makeTicket($event, ['name' => 'Open B']);
        $this->many($event, 9, ['status' => 'sold_out', 'name' => 'Gone']);

        $html = $this->show($event)->assertSee('Sold out (10)')->assertSee('2 ticket types available')->getContent();

        $this->assertMatchesRegularExpression('/id="soldoutToggle"[^>]*aria-expanded="false"/s', $html);   // closed by default
        $this->assertMatchesRegularExpression('/<ul class="tkt-soldout__list" id="soldoutList" hidden>/', $html);
        $sold = substr($html, strpos($html, 'id="soldoutList"'), strpos($html, '</ul>', strpos($html, 'id="soldoutList"')) - strpos($html, 'id="soldoutList"'));
        $this->assertSame(10, substr_count($sold, '<li>'));
        foreach (['data-step', 'data-id', 'tkt-stepper', 'data-price'] as $needle) {
            $this->assertStringNotContainsString($needle, $sold);                         // no quantity control, nothing to add to a cart
        }
        $cards = $this->names($html);
        $this->assertSame(['Open A', 'Open B'], $cards);                                   // only available tickets are selectable cards
        $this->assertLessThan(strpos($html, 'id="soldoutToggle"'), strpos($html, 'Open B'));   // available first, sold out after
    }

    public function test_every_ticket_sold_out_shows_a_clear_state_not_an_empty_purchase_box(): void
    {
        $event = $this->makeEvent();
        $this->many($event, 4, ['status' => 'sold_out']);

        $html = $this->show($event)->assertSee('All tickets are sold out.')->assertSee('Booking closed')->assertSee('Sold out (4)')
            ->assertDontSee('data-ticket-list', false)->assertDontSee('id="orderSummary"', false)->assertDontSee('data-step=', false)->getContent();

        $this->assertStringNotContainsString('ticket types available', $html);
    }

    public function test_long_names_keep_their_full_text_and_the_price_gets_its_own_cell(): void
    {
        $event = $this->makeEvent();
        $name = str_repeat('Extraordinarily-long-ticket-name ', 8).'THE-END';
        $this->makeTicket($event, ['name' => $name, 'description' => 'A short note', 'price' => 99]);

        $html = $this->show($event)->assertSee('THE-END')->assertSee('A short note')->getContent();

        $this->assertStringContainsString('class="tkt-ticket__price"', $html);
        $css = file_get_contents(public_path('css/tkt-v2-pages.css'));
        $this->assertStringContainsString('grid-template-areas: "info price" "limit step"', $css);   // name/price/limit/stepper never share a cell
        $this->assertStringContainsString('overflow-wrap: anywhere', $css);
        $this->assertStringNotContainsString('-webkit-line-clamp', substr($css, strpos($css, '/* Ticket selection (compact)'), 2400));   // the name is never cut
    }

    public function test_couple_and_maximum_rules_and_prices_are_untouched_and_nothing_is_classified(): void
    {
        $event = $this->makeEvent();
        $this->makeTicket($event, ['name' => 'VIP wave 2', 'price' => 800, 'is_couple' => true]);
        $this->makeTicket($event, ['name' => 'Plain', 'price' => 400, 'max_per_order' => 3]);

        $html = $this->show($event)->assertSee('Couples only · 2 tickets')->assertSee('Max 3 per order')->getContent();

        $this->assertMatchesRegularExpression('/data-ticket="VIP wave 2"[^>]*data-price="800.00"[^>]*data-max-qty="2"[^>]*data-is-couple="1"/s', $html);
        $this->assertMatchesRegularExpression('/data-ticket="Plain"[^>]*data-price="400.00"[^>]*data-max-qty="3"[^>]*data-is-couple="0"/s', $html);
        foreach (['tkt-ticket-group', 'data-category', 'data-tier', 'data-wave'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
        $js = file_get_contents(public_path('js/tkt-booking.js'));
        $this->assertDoesNotMatchRegularExpression('/vip|party animal|artist zone|\bwave\b/i', $js);
        $this->assertStringContainsString('var step = isCouple ? 2 : 1;', $js);
    }

    public function test_the_summary_sits_in_the_same_card_after_the_list_and_has_a_bottom_sheet(): void
    {
        $event = $this->makeEvent();
        $this->many($event, 3);

        $html = $this->show($event)->assertSee('id="summaryCount"', false)->assertSee('id="summarySheet"', false)->assertSee('id="sheetBackdrop"', false)->getContent();

        $list = strpos($html, 'data-ticket-list');
        $summary = strpos($html, 'id="orderSummary"');
        $this->assertGreaterThan($list, $summary);
        $card = substr($html, strpos($html, 'tkt-booking__card'));
        $this->assertStringContainsString('id="orderSummary"', substr($card, 0, strpos($card, '</aside>')));   // same card, not a separate column
        $this->assertStringContainsString('viewport-fit=cover', $html);                                        // iOS safe area
    }

    public function test_clear_selection_asks_first_and_the_copy_promises_no_reservation(): void
    {
        $event = $this->makeEvent();
        $this->many($event, 2);

        $html = $this->show($event)->assertSee('Clear selection')->assertSee('Remove all selected tickets?')->assertSee('Yes, clear')->assertSee('Keep')->getContent();

        $this->assertMatchesRegularExpression('/data-clear-confirm[^>]*hidden/', $html);                       // the confirmation is closed until asked for
        $card = substr($html, strpos($html, 'tkt-booking__card'));
        $card = substr($card, 0, strpos($card, '</aside>'));
        foreach (['reserved', 'held for', 'hold your', 'guarantee'] as $promise) {
            $this->assertStringNotContainsStringIgnoringCase($promise, $card);                                  // no promise of holding tickets before payment
        }
        $js = file_get_contents(public_path('js/tkt-booking.js'));
        $this->assertStringContainsString('setters[id](0)', $js);                                              // clearing goes through the normal quantity path
    }

    public function test_layout_rules_full_length_list_and_mobile_fold(): void
    {
        $css = file_get_contents(public_path('css/tkt-v2-pages.css'));

        $this->assertStringNotContainsString('max-height: min(420px, 52vh)', $css);                           // tickets are listed in full length: no inner scroll
        $this->assertStringNotContainsString('max-height: calc(100vh - var(--tkt-header-h) - 32px)', $css);
        $this->assertStringContainsString('.tkt-ticket.is-selected { background: #FFF8D6; border-color: var(--tkt-primary)', $css);
        $this->assertStringContainsString('env(safe-area-inset-bottom)', $css);
        $js = file_get_contents(public_path('js/tkt-booking.js'));
        $this->assertStringContainsString('!card.classList.contains(\'is-selected\')', $js);                   // a selected ticket is never folded away
    }
}
