<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class FrontTicketPublicDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_download_embeds_qr_as_data_uri_for_pdf_rendering(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);

        Ticket::create([
            'name' => 'Test - VIP',
            'price' => 0,
            'status' => 'not_checked_in',
            'ticket_number' => 'ABC123',
            'qr_payload' => 'PAYLOAD123',
        ]);

        Http::fake([
            'api.qrserver.com/*' => Http::response('fake-png-binary', 200),
        ]);

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')
            ->once()
            ->with('ticket-ABC123.pdf')
            ->andReturn(response('pdf:ticket-ABC123.pdf', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data): bool {
                return $view === 'admin.tickets.pdf'
                    && $data['qrDataUri'] === 'data:image/png;base64,'.base64_encode('fake-png-binary')
                    && $data['ticket']->ticket_number === 'ABC123';
            })
            ->andReturn($pdf);

        $url = URL::temporarySignedRoute(
            'front.tickets.public-download',
            now()->addDay(),
            ['ticketNumber' => 'ABC123']
        );

        $this->get($url)
            ->assertOk()
            ->assertSee('pdf:ticket-ABC123.pdf');
    }
}
