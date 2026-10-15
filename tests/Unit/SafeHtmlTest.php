<?php

namespace Tests\Unit;

use App\Support\SafeHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeHtmlTest extends TestCase
{
    public function test_it_keeps_basic_formatting(): void
    {
        $this->assertSame('<p>Hello <strong>world</strong></p>', SafeHtml::clean('<p>Hello <strong>world</strong></p>'));
    }

    public function test_it_removes_scripts_handlers_and_unsafe_links(): void
    {
        $clean = SafeHtml::clean('<p onclick="x()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">bad</a><iframe src="//evil"></iframe>');

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('Hi', $clean);
    }

    public function test_it_hardens_safe_links_and_handles_empty_input(): void
    {
        $this->assertStringContainsString('rel="noopener noreferrer"', SafeHtml::clean('<a href="https://example.com">x</a>'));
        $this->assertSame('', SafeHtml::clean(null));
        $this->assertSame('', SafeHtml::clean('   '));
    }

    #[DataProvider('xssVectors')]
    public function test_it_neutralises_common_xss_vectors(string $payload): void
    {
        $clean = strtolower(SafeHtml::clean($payload));

        foreach (['<script', 'onerror', 'onload', 'onclick', 'onmouseover', 'javascript:', '<iframe', '<svg', '<img', '<style', 'data:text', 'srcdoc', 'vbscript:'] as $needle) {
            $this->assertStringNotContainsString($needle, $clean, "Found {$needle} in: {$clean}");
        }
    }

    public static function xssVectors(): array
    {
        return [
            ['<img src=x onerror=alert(1)>'],
            ['<svg onload=alert(1)>'],
            ['<a href="javascript:alert(1)">x</a>'],
            ['<a href=" JaVaScRiPt:alert(1)">x</a>'],
            ['<a href="java&#x0A;script:alert(1)">x</a>'],
            ['<a href="&#106;avascript:alert(1)">x</a>'],
            ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>'],
            ['<a href="//evil.example/x" onmouseover="alert(1)">x</a>'],
            ['<p style="background:url(javascript:alert(1))">x</p>'],
            ['<iframe srcdoc="<script>alert(1)</script>"></iframe>'],
            ['<noscript><p title="</noscript><img src=x onerror=alert(1)>">'],
            ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>'],
            ['<svg><style><img src=x onerror=alert(1)></style></svg>'],
            ['<!--[if IE]><script>alert(1)</script><![endif]-->'],
            ['<form action="javascript:alert(1)"><button>x</button></form>'],
            ['<div onclick="alert(1)">x</div><script>alert(1)</script>'],
            ['<object data="javascript:alert(1)"></object><embed src="javascript:alert(1)">'],
            ['<a href="vbscript:msgbox(1)">x</a>'],
        ];
    }

    public function test_protocol_relative_links_are_dropped(): void
    {
        $this->assertStringNotContainsString('href', SafeHtml::clean('<a href="//evil.example/">x</a>'));
        $this->assertStringContainsString('href="/events"', SafeHtml::clean('<a href="/events">x</a>'));
    }
}
