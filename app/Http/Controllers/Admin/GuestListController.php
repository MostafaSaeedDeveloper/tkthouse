<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminTicketIssuedMail;
use App\Models\Event;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GuestListController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::query()
            ->invitation()
            ->with('event');

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->input('event_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('holder_name', 'like', "%{$search}%")
                    ->orWhere('holder_email', 'like', "%{$search}%");
            });
        }

        $tickets = $query->latest()->paginate(20)->withQueryString();
        $events = Event::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.guest-list.index', compact('tickets', 'events'));
    }

    public function create()
    {
        $events = Event::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.guest-list.create', compact('events'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'description' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.holder_name' => ['nullable', 'string', 'max:255'],
            'rows.*.holder_email' => ['nullable', 'email', 'max:255'],
        ]);

        $event = Event::query()->findOrFail($data['event_id']);

        $created = collect($data['rows'])
            ->filter(fn (array $row) => filled($row['holder_name'] ?? null) || filled($row['holder_email'] ?? null))
            ->map(function (array $row) use ($event, $data) {
                $name = trim((string) ($row['holder_name'] ?? ''));

                $ticket = Ticket::query()->create([
                    'event_id' => $event->id,
                    'source' => 'invitation',
                    'name' => $event->name.' - Guest List',
                    'description' => $data['description'] ?? null,
                    'status' => 'not_checked_in',
                    'ticket_number' => $this->generateTicketNumber(),
                    'holder_name' => $name !== '' ? $name : 'Guest',
                    'holder_email' => $row['holder_email'] ?? null,
                    'qr_payload' => null,
                    'issued_at' => now(),
                ]);

                $ticket->update(['qr_payload' => $ticket->ticket_number]);

                if (filled($ticket->holder_email)) {
                    $this->sendInvitationEmail($ticket);
                }

                return $ticket;
            });

        if ($created->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['rows' => 'Please add at least one ticket with a name or email.']);
        }

        return redirect()->route('admin.guest-list.index')
            ->with('success', $created->count().' guest invitation(s) created successfully.');
    }

    public function show(Ticket $guest_list)
    {
        abort_unless($guest_list->source === 'invitation', 404);
        $guest_list->load('event');

        return view('admin.guest-list.show', ['ticket' => $guest_list]);
    }

    public function edit(Ticket $guest_list)
    {
        abort_unless($guest_list->source === 'invitation', 404);
        $guest_list->load('event');
        $events = Event::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.guest-list.edit', ['ticket' => $guest_list, 'events' => $events]);
    }

    public function update(Request $request, Ticket $guest_list)
    {
        abort_unless($guest_list->source === 'invitation', 404);

        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:not_checked_in,checked_in,canceled'],
            'holder_name' => ['nullable', 'string', 'max:255'],
            'holder_email' => ['nullable', 'email', 'max:255'],
        ]);

        $event = Event::query()->findOrFail($data['event_id']);

        $guest_list->update([
            ...$data,
            'name' => $event->name.' - Guest List',
            'holder_name' => filled($data['holder_name'] ?? null) ? $data['holder_name'] : 'Guest',
        ]);

        return redirect()->route('admin.guest-list.show', $guest_list)
            ->with('success', 'Guest invitation updated successfully.');
    }

    public function destroy(Ticket $guest_list)
    {
        abort_unless($guest_list->source === 'invitation', 404);
        $guest_list->delete();

        return redirect()->route('admin.guest-list.index')->with('success', 'Guest invitation deleted successfully.');
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'GL'.strtoupper(Str::random(10));
        } while (Ticket::query()->where('ticket_number', $number)->exists());

        return $number;
    }

    private function sendInvitationEmail(Ticket $ticket): void
    {
        $qrDataUri = $this->qrDataUri($ticket);
        $event = $ticket->event;
        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket', 'qrDataUri', 'event'))->output();

        Mail::to($ticket->holder_email)->send(new AdminTicketIssuedMail(
            ticket: $ticket,
            showUrl: route('admin.guest-list.show', $ticket),
            recipientEmail: (string) $ticket->holder_email,
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
}
