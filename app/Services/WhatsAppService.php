<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function sendTicketPdf(string $phone, string $pdfPath, string $message): void
    {
        Log::info('WhatsApp stub', compact('phone', 'pdfPath', 'message'));
    }
}
