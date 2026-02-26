<?php

namespace App\Services;

use App\Mail\TicketMail;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function sendTicket(Ticket $ticket, string $pdfPath): void
    {
        $email = $ticket->order->customer_email ?? $ticket->order->user?->email;
        if ($email) {
            Mail::to($email)->send(new TicketMail($ticket, $pdfPath));
        }
    }
}
