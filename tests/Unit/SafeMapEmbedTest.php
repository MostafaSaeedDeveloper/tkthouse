<?php

namespace Tests\Unit;

use App\Support\SafeMapEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeMapEmbedTest extends TestCase
{
    public function test_it_accepts_a_google_maps_embed_and_rebuilds_the_iframe(): void
    {
        $html = SafeMapEmbed::render('<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12" width="600" height="450" style="border:0" onload="alert(1)" allowfullscreen></iframe>');

        $this->assertStringContainsString('src="https://www.google.com/maps/embed?pb=!1m18!1m12"', $html);
        $this->assertStringNotContainsString('onload', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringContainsString('sandbox=', $html);
    }

    public function test_it_accepts_other_approved_providers_and_bare_urls(): void
    {
        $this->assertNotNull(SafeMapEmbed::url('https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4'));
        $this->assertNotNull(SafeMapEmbed::url('https://www.google.com/maps?q=cairo&output=embed'));
        $this->assertNotNull(SafeMapEmbed::url('https://www.google.com/maps/embed?pb=abc'));
    }

    #[DataProvider('hostileValues')]
    public function test_it_rejects_untrusted_markup(string $value): void
    {
        $this->assertNull(SafeMapEmbed::url($value));
        $this->assertSame('', SafeMapEmbed::render($value));
    }

    public static function hostileValues(): array
    {
        return [
            'script tag' => ['<script>alert(1)</script>'],
            'script wrapped around valid iframe' => ['<script>alert(1)</script><img src=x onerror=alert(1)>'],
            'javascript url' => ['<iframe src="javascript:alert(1)"></iframe>'],
            'data url' => ['<iframe src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=="></iframe>'],
            'untrusted host' => ['<iframe src="https://evil.example.com/maps/embed"></iframe>'],
            'lookalike host' => ['<iframe src="https://www.google.com.evil.example/maps/embed"></iframe>'],
            'userinfo trick' => ['<iframe src="https://www.google.com@evil.example/maps/embed"></iframe>'],
            'plain http' => ['<iframe src="http://www.google.com/maps/embed?pb=1"></iframe>'],
            'protocol relative' => ['<iframe src="//www.google.com/maps/embed?pb=1"></iframe>'],
            'google non-embed page' => ['https://www.google.com/search?q=x'],
            'google maps page without embed output' => ['https://www.google.com/maps?q=cairo'],
            'quote break out' => ['<iframe src="https://www.google.com/maps/embed?pb=1&quot; onload=&quot;alert(1)"></iframe>'],
            'iframe without src' => ['<iframe srcdoc="<script>alert(1)</script>"></iframe>'],
            'empty' => [''],
        ];
    }

    public function test_output_escapes_the_url(): void
    {
        $html = SafeMapEmbed::render('https://www.google.com/maps/embed?pb=1&x=2');
        $this->assertStringContainsString('pb=1&amp;x=2', $html);
    }
}
