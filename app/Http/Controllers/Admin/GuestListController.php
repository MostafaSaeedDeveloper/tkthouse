<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminTicketIssuedMail;
use App\Models\Event;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestListController extends Controller
{
    public function index(Request $request)
    {
        $ticketsQuery = Ticket::query()->with('order')->where('source', 'guest_list');

        if ($request->filled('event_name')) {
            $eventName = trim((string) $request->input('event_name'));
            $ticketsQuery->where('name', 'like', $eventName.' - %');
        }

        if ($request->filled('guest_type')) {
            $ticketsQuery->where('guest_type', trim((string) $request->input('guest_type')));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $ticketsQuery->where(function ($query) use ($search) {
                $query->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('holder_name', 'like', "%{$search}%")
                    ->orWhere('holder_email', 'like', "%{$search}%");
            });
        }

        $tickets = $ticketsQuery->latest()->paginate(15)->withQueryString();
        $eventNames = Event::query()->orderBy('name')->pluck('name');
        $guestTypes = Ticket::query()->where('source', 'guest_list')->whereNotNull('guest_type')->distinct()->orderBy('guest_type')->pluck('guest_type');

        return view('admin.tickets.guest-list', compact('tickets', 'eventNames', 'guestTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'guest_type' => ['required', 'string', 'max:255'],
            'guests' => ['required', 'array', 'min:1'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:not_checked_in,checked_in,canceled'],
        ]);

        $status = $data['status'] ?? 'not_checked_in';
        $guestType = $this->normalizeGuestType($data['guest_type']);

        foreach ($data['guests'] as $guest) {
            $ticketNumber = $this->generateTicketNumber();
            $ticket = Ticket::create([
                'name' => $data['event_name'].' - '.$guestType,
                'source' => 'guest_list',
                'guest_type' => $guestType,
                'description' => 'Guest list invitation',
                'status' => $status,
                'holder_name' => $guest['name'],
                'holder_email' => $guest['email'] ?? null,
                'holder_phone' => $guest['phone'] ?? null,
                'ticket_number' => $ticketNumber,
                'qr_payload' => $ticketNumber,
                'issued_at' => now(),
            ]);

            $this->sendTicketEmailIfProvided($ticket);
        }

        return redirect()->route('admin.guest-list.index')->with('success', 'Guest invitations created successfully.');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['event_name', 'guest_type', 'name', 'email', 'phone', 'quantity']);
            fputcsv($handle, ['Sample Event', 'Regular', 'Guest Name', 'guest@example.com', '01000000000', '1']);
            fclose($handle);
        }, 'guest-list-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $rows = collect(array_map('str_getcsv', file($data['file']->getRealPath())));
        if ($rows->isEmpty()) {
            return back()->with('error', 'Import file is empty.');
        }

        $headers = $rows->shift()->map(fn ($header) => trim((string) $header));

        $created = 0;

        foreach ($rows as $row) {
            if (count($row) === 1 && blank($row[0])) {
                continue;
            }

            $mapped = $headers->combine($row);
            if (! $mapped instanceof Collection) {
                continue;
            }

            $eventName = trim((string) $mapped->get('event_name', ''));
            $name = trim((string) $mapped->get('name', ''));

            if ($eventName === '' || $name === '') {
                continue;
            }

            $guestType = $this->normalizeGuestType((string) $mapped->get('guest_type', 'Regular'));
            $email = trim((string) $mapped->get('email', '')) ?: null;
            $phone = trim((string) $mapped->get('phone', '')) ?: null;
            $quantity = max(1, (int) $mapped->get('quantity', 1));

            for ($i = 0; $i < $quantity; $i++) {
                $ticketNumber = $this->generateTicketNumber();
                $ticket = Ticket::create([
                    'name' => $eventName.' - '.$guestType,
                    'source' => 'guest_list',
                    'guest_type' => $guestType,
                    'description' => 'Imported guest list invitation',
                    'status' => 'not_checked_in',
                    'holder_name' => $name,
                    'holder_email' => $email,
                    'holder_phone' => $phone,
                    'ticket_number' => $ticketNumber,
                    'qr_payload' => $ticketNumber,
                    'issued_at' => now(),
                ]);

                $this->sendTicketEmailIfProvided($ticket);
                $created++;
            }
        }

        return back()->with('success', "Import finished: {$created} guest tickets created.");
    }

    public function export(): StreamedResponse
    {
        $tickets = Ticket::query()->where('source', 'guest_list')->latest()->get();

        return response()->streamDownload(function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ticket_number', 'event_name', 'guest_type', 'name', 'email', 'phone', 'status']);

            foreach ($tickets as $ticket) {
                fputcsv($handle, [
                    $ticket->ticket_number,
                    $ticket->eventLabel(),
                    $ticket->guest_type,
                    $ticket->holder_name,
                    $ticket->holder_email,
                    $ticket->holder_phone,
                    $ticket->status,
                ]);
            }

            fclose($handle);
        }, 'guest-list-export.csv', ['Content-Type' => 'text/csv']);
    }

    private function normalizeGuestType(string $guestType): string
    {
        $trimmed = trim($guestType);
        $withoutPrefix = preg_replace('/^guest\s+/i', '', $trimmed) ?: 'Regular';

        return 'Guest '.trim($withoutPrefix);
    }

    private function sendTicketEmailIfProvided(Ticket $ticket): void
    {
        if (! filled($ticket->holder_email)) {
            return;
        }

        $pdf = Pdf::loadView('admin.tickets.pdf', [
            'ticket' => $ticket,
            'qrDataUri' => $ticket->qr_payload
                ? 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode((string) $ticket->qr_payload)
                : 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode((string) $ticket->ticket_number),
            'event' => Event::query()->where('name', $ticket->eventLabel())->first(),
        ])->output();

        Mail::to($ticket->holder_email)->send(new AdminTicketIssuedMail(
            ticket: $ticket,
            showUrl: route('admin.tickets.show', $ticket),
            recipientEmail: $ticket->holder_email,
            pdfBinary: $pdf,
        ));
    }

    private function generateTicketNumber(): string
    {
        do {
            $ticketNumber = (string) random_int(1000000000, 9999999999);
        } while (Ticket::query()->where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }
}
