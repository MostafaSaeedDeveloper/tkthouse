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

    public function create(Request $request)
    {
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'guest_type' => ['required', 'string', 'max:255'],
            'count' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $eventNames = Event::query()->orderBy('name')->pluck('name');

        return view('admin.tickets.guest-list-create', [
            'eventNames' => $eventNames,
            'selectedEventName' => $data['event_name'],
            'selectedGuestType' => $this->normalizeGuestType($data['guest_type']),
            'selectedCount' => (int) ($data['count'] ?? 1),
        ]);
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
            'guests.*.gender' => ['nullable', 'in:male,female'],
            'status' => ['nullable', 'in:not_checked_in,checked_in,canceled'],
        ]);

        $status = $data['status'] ?? 'not_checked_in';
        $guestType = $this->normalizeGuestType($data['guest_type']);

        foreach ($data['guests'] as $guest) {
            $this->createGuestTicket(
                eventName: $data['event_name'],
                guestType: $guestType,
                name: $guest['name'],
                email: $guest['email'] ?? null,
                phone: $guest['phone'] ?? null,
                gender: $guest['gender'] ?? null,
                status: $status,
                description: 'Guest list invitation',
            );
        }

        return redirect()->route('admin.guest-list.index')->with('success', 'Guest invitations created successfully.');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['guest_type', 'name', 'email', 'phone', 'gender', 'quantity']);
            fputcsv($handle, ['Regular', 'Guest Name', 'guest@example.com', '01000000000', 'male', '1']);
            fclose($handle);
        }, 'guest-list-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
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

            $name = trim((string) $mapped->get('name', ''));

            if ($name === '') {
                continue;
            }

            $guestType = $this->normalizeGuestType((string) $mapped->get('guest_type', 'Regular'));
            $email = trim((string) $mapped->get('email', '')) ?: null;
            $phone = trim((string) $mapped->get('phone', '')) ?: null;
            $gender = in_array(mb_strtolower(trim((string) $mapped->get('gender', ''))), ['male', 'female'], true)
                ? mb_strtolower(trim((string) $mapped->get('gender', '')))
                : null;
            $quantity = max(1, (int) $mapped->get('quantity', 1));

            for ($i = 0; $i < $quantity; $i++) {
                $this->createGuestTicket(
                    eventName: $data['event_name'],
                    guestType: $guestType,
                    name: $name,
                    email: $email,
                    phone: $phone,
                    gender: $gender,
                    status: 'not_checked_in',
                    description: 'Imported guest list invitation',
                );

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
            fputcsv($handle, ['ticket_number', 'event_name', 'guest_type', 'name', 'email', 'phone', 'gender', 'status']);

            foreach ($tickets as $ticket) {
                fputcsv($handle, [
                    $ticket->ticket_number,
                    $ticket->eventLabel(),
                    $ticket->guest_type,
                    $ticket->holder_name,
                    $ticket->holder_email,
                    $ticket->holder_phone,
                    $ticket->holder_gender,
                    $ticket->status,
                ]);
            }

            fclose($handle);
        }, 'guest-list-export.csv', ['Content-Type' => 'text/csv']);
    }

    private function createGuestTicket(
        string $eventName,
        string $guestType,
        string $name,
        ?string $email,
        ?string $phone,
        ?string $gender,
        string $status,
        string $description,
    ): void {
        $ticketNumber = $this->generateTicketNumber();

        $ticket = Ticket::create([
            'name' => $eventName.' - '.$guestType,
            'source' => 'guest_list',
            'guest_type' => $guestType,
            'description' => $description,
            'status' => $status,
            'holder_name' => $name,
            'holder_email' => $email,
            'holder_phone' => $phone,
            'holder_gender' => $gender,
            'ticket_number' => $ticketNumber,
            'qr_payload' => $ticketNumber,
            'issued_at' => now(),
        ]);

        $this->sendTicketEmailIfProvided($ticket);
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
