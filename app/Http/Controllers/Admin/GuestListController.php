<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\GuestInvitationMail;
use App\Models\Event;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestListController extends Controller
{
    public function index(Request $request)
    {
        $managedEventName = $request->user()?->managedEvent?->name;

        $ticketsQuery = Ticket::query()->with('order')->where('source', 'guest_list');
        $this->applyManagedEventScopeToGuestTicketsQuery($ticketsQuery, $managedEventName);

        if ($request->filled('event_name')) {
            $eventName = trim((string) $request->input('event_name'));
            $ticketsQuery->where(function (Builder $query) use ($eventName) {
                $query->where('name', 'like', $eventName.' - %')
                    ->orWhere('name', $eventName);
            });
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

        $eventNames = Event::query()
            ->when(filled($managedEventName), fn (Builder $query) => $query->where('name', $managedEventName))
            ->orderBy('name')
            ->pluck('name');

        $guestTypesQuery = Ticket::query()->where('source', 'guest_list')->whereNotNull('guest_type');
        $this->applyManagedEventScopeToGuestTicketsQuery($guestTypesQuery, $managedEventName);
        $guestTypes = $guestTypesQuery->distinct()->orderBy('guest_type')->pluck('guest_type');

        return view('admin.tickets.guest-list', compact('tickets', 'eventNames', 'guestTypes'));
    }

    public function create(Request $request)
    {
        $managedEventName = $request->user()?->managedEvent?->name;
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'guest_type' => ['required', 'string', 'max:255'],
            'count' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $this->abortIfManagedEventMismatch($managedEventName, $data['event_name']);

        $eventNames = Event::query()
            ->when(filled($managedEventName), fn (Builder $query) => $query->where('name', $managedEventName))
            ->orderBy('name')
            ->pluck('name');

        return view('admin.tickets.guest-list-create', [
            'eventNames' => $eventNames,
            'selectedEventName' => $data['event_name'],
            'selectedGuestType' => $this->normalizeGuestType($data['guest_type']),
            'selectedCount' => (int) ($data['count'] ?? 1),
        ]);
    }

    public function store(Request $request)
    {
        $managedEventName = $request->user()?->managedEvent?->name;
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

        $this->abortIfManagedEventMismatch($managedEventName, $data['event_name']);

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
            fputcsv($handle, ['guest_type', 'name', 'email', 'phone', 'gender']);
            fputcsv($handle, ['Regular', 'Guest Name', 'guest@example.com', '01000000000', 'male']);
            fclose($handle);
        }, 'guest-list-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $managedEventName = $request->user()?->managedEvent?->name;
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $this->abortIfManagedEventMismatch($managedEventName, $data['event_name']);

        $rows = collect(array_map('str_getcsv', file($data['file']->getRealPath())));

        if ($rows->isEmpty()) {
            return back()->with('error', 'Import file is empty.');
        }

        $headers = collect($rows->shift() ?? [])->map(fn ($header) => trim((string) $header));
        if ($headers->isEmpty()) {
            return back()->with('error', 'Import file has no header row.');
        }

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

        return back()->with('success', "Import finished: {$created} guest tickets created.");
    }

    public function export(Request $request): StreamedResponse
    {
        $managedEventName = $request->user()?->managedEvent?->name;

        $ticketsQuery = Ticket::query()->where('source', 'guest_list');
        $this->applyManagedEventScopeToGuestTicketsQuery($ticketsQuery, $managedEventName);

        $tickets = $ticketsQuery->latest()->get();

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


    private function applyManagedEventScopeToGuestTicketsQuery(Builder $query, ?string $eventName): void
    {
        if (! filled($eventName)) {
            return;
        }

        $query->where(function (Builder $ticketQuery) use ($eventName) {
            $ticketQuery->where('name', 'like', $eventName.' - %')
                ->orWhere('name', $eventName);
        });
    }

    private function abortIfManagedEventMismatch(?string $managedEventName, string $requestedEventName): void
    {
        if (! filled($managedEventName)) {
            return;
        }

        abort_unless(trim($managedEventName) === trim($requestedEventName), 403);
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
            'qrDataUri' => $this->qrDataUri($ticket),
            'event' => Event::query()->where('name', $ticket->eventLabel())->first(),
        ])->output();

        Mail::to($ticket->holder_email)->send(new GuestInvitationMail(
            ticket: $ticket,
            pdfBinary: $pdf,
        ));
    }


    private function qrDataUri(Ticket $ticket): string
    {
        $payload = $ticket->qr_payload ?: $ticket->ticket_number;
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode((string) $payload);

        try {
            $response = Http::timeout(15)->get($url);
            if ($response->successful()) {
                return 'data:image/png;base64,'.base64_encode($response->body());
            }
        } catch (\Throwable) {
            // Ignore and fallback to URL
        }

        return $url;
    }

    private function generateTicketNumber(): string
    {
        do {
            $ticketNumber = (string) random_int(1000000000, 9999999999);
        } while (Ticket::query()->where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }
}
