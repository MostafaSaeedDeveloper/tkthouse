<?php

namespace Tests\Feature;

use App\Services\UltramsgWhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Staging safety: with a production data copy, nothing may reach real customers. */
class NotificationGuardTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function sent(): int
    {
        return count(Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    private function send(string $to): void
    {
        Mail::mailer('array')->raw('hello', fn ($m) => $m->to($to)->subject('Test'));
    }

    public function test_mail_is_unrestricted_when_no_allowlist_is_configured(): void
    {
        config(['notifications.mail_allowlist' => null]);
        $before = $this->sent();

        $this->send('customer@example.com');

        $this->assertSame($before + 1, $this->sent());
    }

    public function test_none_blocks_every_email(): void
    {
        config(['notifications.mail_allowlist' => []]);
        $before = $this->sent();

        $this->send('real.customer@example.com');

        $this->assertSame($before, $this->sent());
    }

    public function test_only_allowlisted_addresses_and_domains_receive_mail(): void
    {
        config(['notifications.mail_allowlist' => ['qa@example.org', '@tkthouse.com']]);
        $before = $this->sent();

        $this->send('real.customer@example.com');
        $this->assertSame($before, $this->sent());

        $this->send('QA@example.org');
        $this->send('dev@tkthouse.com');
        $this->assertSame($before + 2, $this->sent());
    }

    public function test_a_mixed_recipient_list_is_trimmed_to_the_allowed_ones(): void
    {
        config(['notifications.mail_allowlist' => ['qa@example.org']]);
        $before = $this->sent();

        Mail::mailer('array')->raw('hello', fn ($m) => $m->to(['qa@example.org', 'victim@example.com'])->subject('Test'));

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertSame($before + 1, count($messages));
        $recipients = collect($messages->last()->getOriginalMessage()->getTo())->map->getAddress()->all();
        $this->assertSame(['qa@example.org'], $recipients);
    }

    public function test_whatsapp_can_be_switched_off_by_the_environment_even_if_the_stored_setting_is_on(): void
    {
        Http::preventStrayRequests();
        config(['notifications.whatsapp_enabled' => false]);
        $order = $this->makePendingOrder(\App\Models\User::factory()->create());

        $this->assertFalse(app(UltramsgWhatsappService::class)->sendOrderTickets($order));
        Http::assertNothingSent();
    }

    public function test_whatsapp_phone_allowlist_skips_unlisted_numbers(): void
    {
        Http::preventStrayRequests();
        config(['notifications.whatsapp_enabled' => true, 'notifications.phone_allowlist' => ['+201111111111']]);
        $order = $this->makePendingOrder(\App\Models\User::factory()->create());
        $order->customer->update(['phone' => '+201000000000']);

        $this->assertFalse(app(UltramsgWhatsappService::class)->sendOrderTickets($order->fresh()));
        Http::assertNothingSent();
    }
}
