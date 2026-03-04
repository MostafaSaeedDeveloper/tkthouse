<?php

namespace Tests\Unit;

use App\Models\IssuedTicket;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class IssuedTicketQrDataUriTest extends TestCase
{
    public function test_it_returns_data_uri_when_qr_service_succeeds(): void
    {
        Http::fake([
            'api.qrserver.com/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $ticket = new IssuedTicket([
            'uuid' => (string) Str::uuid(),
            'ticket_number' => 'TKT-12345678',
        ]);

        $dataUri = $ticket->qrDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
        $this->assertSame('png-bytes', base64_decode(substr($dataUri, strlen('data:image/png;base64,'))));
    }

    public function test_it_falls_back_to_remote_url_when_qr_service_fails(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Failed to connect');
        });

        $ticket = new IssuedTicket([
            'uuid' => (string) Str::uuid(),
            'ticket_number' => 'TKT-12345678',
        ]);

        $this->assertSame($ticket->qrUrl(), $ticket->qrDataUri());
    }
}
