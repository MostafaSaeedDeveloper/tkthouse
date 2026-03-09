<?php

namespace Tests\Feature;

use App\Services\WhatsappMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_actionable_reason_for_twilio_63007_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'code' => 63007,
                'message' => 'Twilio could not find a Channel with the specified From address',
            ], 400),
        ]);

        config()->set('services.twilio.account_sid', 'AC_TEST');
        config()->set('services.twilio.auth_token', 'token_test');
        config()->set('services.twilio.whatsapp_from', 'whatsapp:+12345');

        $result = app(WhatsappMessageService::class)->sendText('01000000000', 'test');

        $this->assertFalse($result['sent']);
        $this->assertFalse($result['skipped']);
        $this->assertStringContainsString('twilio_63007_invalid_whatsapp_from', (string) $result['reason']);
    }

    public function test_it_uses_messaging_service_sid_when_configured(): void
    {
        Http::fake([
            '*' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ], 201),
        ]);

        config()->set('services.twilio.account_sid', 'AC_TEST');
        config()->set('services.twilio.auth_token', 'token_test');
        config()->set('services.twilio.messaging_service_sid', 'MGXXXX');
        config()->set('services.twilio.whatsapp_from', '');

        $result = app(WhatsappMessageService::class)->sendText('01000000000', 'test');

        $this->assertTrue($result['sent']);

        Http::assertSent(function ($request) {
            return $request['MessagingServiceSid'] === 'MGXXXX'
                && $request['To'] === 'whatsapp:+201000000000';
        });
    }
}
