<?php

namespace Tests\Unit;

use App\Services\UltramsgWhatsappService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class UltramsgWhatsappServiceTest extends TestCase
{
    public function test_document_send_prefers_prefetched_pdf_base64_before_public_urls(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://example.test',
            'services.ultramsg.base_url' => 'https://api.ultramsg.com',
            'services.ultramsg.instance_id' => 'instance123',
            'services.ultramsg.token' => 'token123',
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/tickets/public/TICKET-1/download')) {
                return Http::response('%PDF-with-working-qr');
            }

            if ($request->url() === 'https://api.ultramsg.com/instance123/messages/document') {
                return Http::response(['sent' => true]);
            }

            return Http::response('', 404);
        });

        $this->invokeSendDocumentWithFallbacks('TICKET-1');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.ultramsg.com/instance123/messages/document'
                && $request['document'] === base64_encode('%PDF-with-working-qr')
                && $request['filename'] === 'ticket-TICKET-1.pdf';
        });
    }

    private function invokeSendDocumentWithFallbacks(string $ticketNumber): void
    {
        $method = new ReflectionMethod(UltramsgWhatsappService::class, 'sendDocumentWithFallbacks');
        $method->setAccessible(true);

        $method->invoke(
            new UltramsgWhatsappService,
            '+201000000000',
            $ticketNumber,
            'Ticket PDF',
            'Download Link',
            []
        );
    }
}
