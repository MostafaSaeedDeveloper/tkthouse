<?php

namespace Tests\Unit;

use App\Support\SafeHtml;
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
}
