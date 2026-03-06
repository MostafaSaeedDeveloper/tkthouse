<?php

namespace App\Services;

use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public function sendPaymentConfirmed(Order $order): void
    {
        $customerName = trim((string) ($order->customer?->full_name ?: 'عميلنا العزيز'));

        $this->sendText(
            (string) $order->customer?->phone,
            "مرحباً {$customerName}، تم الدفع بنجاح لطلبك رقم {$order->order_number}."
        );
    }

    public function sendOrderTickets(Order $order): void
    {
        $ticketsSummary = $order->issuedTickets
            ->map(fn (IssuedTicket $ticket) => $ticket->ticket_number)
            ->filter()
            ->implode('، ');

        $message = "تذاكر الطلب رقم {$order->order_number} جاهزة. أرقام التذاكر: {$ticketsSummary}.";

        $firstTicket = $order->issuedTickets->first();
        if ($firstTicket) {
            $message .= "\nعرض التذكرة: ".route('front.tickets.show', $firstTicket);
        }

        $this->sendText((string) $order->customer?->phone, $message);
    }

    public function sendTicketDetails(Ticket $ticket): void
    {
        $message = "رقم التذكرة: {$ticket->ticket_number}.\nعرض التذكرة: ".route('admin.tickets.show', $ticket)
            ."\nتحميل PDF: ".route('admin.tickets.download', $ticket);

        $this->sendText((string) $ticket->holder_phone, $message);
    }

    private function sendText(string $phone, string $message): void
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return;
        }

        $baseUrl = trim((string) config('services.whatsapp.base_url'));
        $token = trim((string) config('services.whatsapp.token'));

        if ($baseUrl !== '' && $token !== '') {
            $endpoint = rtrim($baseUrl, '/').'/api/v1/sendSessionMessage/'.$phone;
            $query = [];
            $whatsappNumber = trim((string) config('services.whatsapp.number'));
            if ($whatsappNumber !== '') {
                $query['whatsappNumber'] = $whatsappNumber;
            }

            Http::timeout(15)
                ->withHeaders(['Authorization' => $token])
                ->asJson()
                ->post($endpoint.(empty($query) ? '' : '?'.http_build_query($query)), [
                    'messageText' => $message,
                ])
                ->throw();

            return;
        }

        $webhookUrl = trim((string) config('services.whatsapp.webhook_url'));
        if ($webhookUrl === '') {
            return;
        }

        Http::timeout(15)
            ->withToken((string) config('services.whatsapp.webhook_token'))
            ->post($webhookUrl, [
                'phone' => $phone,
                'message' => $message,
            ])
            ->throw();
    }

    private function normalizePhone(string $phone): string
    {
        $value = preg_replace('/[^\d+]/', '', trim($phone));
        if (! $value) {
            return '';
        }

        if (str_starts_with($value, '00')) {
            return substr($value, 2);
        }

        return ltrim($value, '+');
    }
}

