<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontTicketController;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class FrontTicketQrDataUriTest extends TestCase
{
    public function test_qr_data_uri_embeds_successful_qr_response(): void
    {
        Http::fake([
            'api.qrserver.com/*' => Http::response('png-bytes'),
        ]);

        $qrDataUri = $this->invokeQrDataUri('ticket payload');

        $this->assertSame('data:image/png;base64,'.base64_encode('png-bytes'), $qrDataUri);
        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'data=ticket+payload'));
    }

    public function test_qr_data_uri_falls_back_to_remote_url_when_fetch_fails(): void
    {
        Http::fake([
            'api.qrserver.com/*' => Http::response('', 500),
        ]);

        $qrDataUri = $this->invokeQrDataUri('ticket payload');

        $this->assertSame(
            'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=ticket+payload',
            $qrDataUri
        );
    }

    private function invokeQrDataUri(string $payload): string
    {
        $method = new ReflectionMethod(FrontTicketController::class, 'qrDataUri');
        $method->setAccessible(true);

        return $method->invoke(new FrontTicketController, $payload);
    }
}
