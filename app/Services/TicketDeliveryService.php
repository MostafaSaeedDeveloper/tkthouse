<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;

class TicketDeliveryService
{
    public function deliverOrderTickets(Order $order): void
    {
        foreach ($order->tickets as $ticket) {
            $pdfPath = app(TicketsPdfService::class)->generate($ticket);

            if ($this->enabled('email_enabled')) {
                app(EmailService::class)->sendTicket($ticket, $pdfPath);
            }

            if ($this->enabled('whatsapp_enabled') && ($order->customer_phone || $order->user?->phone)) {
                app(WhatsAppService::class)->sendTicketPdf(
                    $order->customer_phone ?? $order->user?->phone,
                    $pdfPath,
                    'تذكرتك من TKT House جاهزة.'
                );
            }
        }
    }

    protected function enabled(string $key): bool
    {
        return filter_var(Setting::where('key', $key)->value('value') ?? false, FILTER_VALIDATE_BOOL);
    }
}
