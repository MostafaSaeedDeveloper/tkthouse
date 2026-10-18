<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Soft navigation (js/tkt-spa.js) is progressive enhancement: every public page must still be a complete
 * server-rendered document, with its own SEO tags, whether or not any JavaScript runs.
 */
class SoftNavigationTest extends TestCase
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
            'description' => '<p>A long night of <strong>techno</strong>.</p><script>alert(1)</script>',
            'status' => 'active',
        ], $overrides));
    }

    public function test_every_public_page_is_a_complete_document_with_seo_tags(): void
    {
        $event = $this->makeEvent();

        $pages = [
            route('front.home') => route('front.home'),
            route('front.events') => route('front.events'),
            route('front.events.show', $event) => route('front.events.show', $event),
            route('front.about') => route('front.about'),
            route('front.contact') => route('front.contact'),
            route('front.terms') => route('front.terms'),
            route('front.privacy') => route('front.privacy'),
            route('front.cookie') => route('front.cookie'),
        ];

        foreach ($pages as $url => $canonical) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<main id="tkt-main"', false)
                ->assertSee('<meta name="description" content="', false)
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
                ->assertSee('<meta property="og:title"', false)
                ->assertSee('<meta property="og:url" content="'.$canonical.'">', false)
                ->assertSee('<meta property="og:image"', false);
        }
    }

    public function test_canonical_drops_search_and_filter_queries_but_keeps_pagination(): void
    {
        $this->makeEvent();

        $this->get(route('front.events', ['q' => 'urban', 'when' => 'upcoming']))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('front.events').'">', false);

        $this->get(route('front.events', ['page' => 2]))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('front.events').'?page=2">', false);
    }

    public function test_event_meta_description_is_plain_text_and_never_leaks_unsafe_markup(): void
    {
        $event = $this->makeEvent();

        $this->get(route('front.events.show', $event))
            ->assertOk()
            ->assertSee('<meta name="description" content="A long night of techno.">', false)
            ->assertDontSee('alert(1)', false);
    }

    public function test_layout_loads_the_lifecycle_and_router_scripts_once_and_in_order(): void
    {
        $html = $this->get(route('front.home'))->assertOk()->getContent();

        $order = ['js/tkt-page.js', 'js/tkt-v2.js', 'js/tkt-booking.js', 'js/vendor/swup-4.8.2.umd.js', 'js/tkt-spa.js'];
        $last = -1;
        foreach ($order as $script) {
            $this->assertSame(1, substr_count($html, $script), "$script should be included exactly once");
            $pos = strpos($html, $script);
            $this->assertGreaterThan($last, $pos, "$script is out of order");
            $last = $pos;
        }

        // The event page no longer pushes the booking script itself (it is part of the layout now).
        $event = $this->makeEvent();
        $this->assertSame(1, substr_count($this->get(route('front.events.show', $event))->getContent(), 'js/tkt-booking.js'));
    }

    public function test_vendored_router_files_exist(): void
    {
        foreach ([
            'swup-4.8.2.umd.js', 'swup-head-plugin-2.3.1.umd.js', 'swup-a11y-plugin-5.2.1.umd.js',
            'swup-scroll-plugin-4.0.0.umd.js', 'swup-progress-plugin-3.2.0.umd.js',
        ] as $file) {
            $this->assertFileExists(public_path('js/vendor/'.$file));
        }
    }

    public function test_sensitive_pages_stay_outside_the_soft_navigation_allow_list(): void
    {
        $js = file_get_contents(public_path('js/tkt-spa.js'));

        // The allow-list is the only place that can opt a path in; none of these may ever appear in it.
        preg_match('/PUBLIC_PATHS = \[(.*?)\];/s', $js, $m);
        $this->assertNotEmpty($m, 'PUBLIC_PATHS allow-list not found');
        foreach (['checkout', 'payment', 'account', 'login', 'register', 'logout', 'dashboard', 'tickets', 'callback', 'webhook', 'otp', 'password'] as $word) {
            $this->assertStringNotContainsString($word, $m[1], "$word must never be soft-navigable");
        }
    }
}
