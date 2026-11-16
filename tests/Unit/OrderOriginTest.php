<?php

namespace Tests\Unit;

use App\Support\OrderOrigin;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;

class OrderOriginTest extends TestCase
{
    private function request(string $uri, array $headers = []): Request
    {
        $request = Request::create($uri, 'GET', [], [], [], array_merge(['HTTP_HOST' => 'tkthouse.test'], $headers));
        $request->setLaravelSession(new Store('t', new ArraySessionHandler(120)));

        return $request;
    }

    public function test_normalize_makes_a_clean_slug(): void
    {
        $this->assertSame('instagram', OrderOrigin::normalize(' Instagram '));
        $this->assertSame('ig_story', OrderOrigin::normalize('IG Story!'));
        $this->assertNull(OrderOrigin::normalize('  '));
    }

    public function test_utm_source_is_captured_and_first_touch_wins(): void
    {
        $r = $this->request('/events?utm_source=Instagram');
        OrderOrigin::capture($r);
        $this->assertSame('instagram', OrderOrigin::forCheckout($r));

        $r2 = Request::create('/events?utm_source=facebook');
        $r2->setLaravelSession($r->session());
        OrderOrigin::capture($r2);
        $this->assertSame('instagram', OrderOrigin::forCheckout($r2));
    }

    public function test_external_referrer_is_mapped(): void
    {
        $r = $this->request('/events', ['HTTP_REFERER' => 'https://l.instagram.com/?u=x']);
        OrderOrigin::capture($r);
        $this->assertSame('instagram', OrderOrigin::forCheckout($r));
    }

    public function test_defaults_to_affiliate_or_organic(): void
    {
        $r = $this->request('/events', ['HTTP_REFERER' => 'https://tkthouse.test/home']);
        OrderOrigin::capture($r);
        $this->assertSame('organic', OrderOrigin::forCheckout($r));
        $this->assertSame('affiliate', OrderOrigin::forCheckout($r, 5));
    }
}
