<?php

namespace App\Http\Controllers\Admin;

use App\Mail\AdminTicketIssuedMail;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $ticketsQuery = Ticket::query()->with('order');

        if ($request->filled('status')) {
            $ticketsQuery->where('status', $request->string('status'));
        }

        if ($request->filled('event_name')) {
            $eventName = trim((string) $request->input('event_name'));
            $ticketsQuery->where('name', 'like', $eventName.' - %');
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

        return view('admin.tickets.index', compact('tickets', 'eventNames'));
    }

    public function create()
    {
        return view('admin.tickets.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateTicket($request);

        Ticket::create($validated + ['name' => $validated['holder_name'] ?? 'Ticket']);

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('order');

        return view('admin.tickets.show', compact('ticket'));
    }

    public function edit(Ticket $ticket)
    {
        $ticket->load('order');

        return view('admin.tickets.edit', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $this->validateTicket($request);

        $ticket->update($validated);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    public function sendEmail(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $ticket->loadMissing('order');
        $qrDataUri = $this->qrDataUri($ticket);

        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket', 'qrDataUri'))->output();
        $showUrl = route('admin.tickets.show', $ticket);

        Mail::to($data['email'])->send(new AdminTicketIssuedMail(
            ticket: $ticket,
            showUrl: $showUrl,
            recipientEmail: $data['email'],
            pdfBinary: $pdf,
        ));

        return back()->with('success', 'Ticket sent by email successfully.');
    }

    public function sendWhatsapp(Ticket $ticket)
    {
        $url = config('services.whatsapp.webhook_url');
        if (! $url) {
            return back()->with('error', 'WhatsApp webhook is not configured.');
        }

        Http::timeout(15)
            ->withToken((string) config('services.whatsapp.token'))
            ->post($url, [
                'ticket_number' => $ticket->ticket_number,
                'holder_name' => $ticket->holder_name,
                'holder_phone' => $ticket->holder_phone,
                'ticket_show_url' => route('admin.tickets.show', $ticket),
                'ticket_pdf_url' => route('admin.tickets.download', $ticket),
            ])
            ->throw();

        return back()->with('success', 'Ticket sent by WhatsApp webhook successfully.');
    }

    public function download(Ticket $ticket)
    {
        $ticket->loadMissing('order');
        $qrDataUri = $this->qrDataUri($ticket);

        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket', 'qrDataUri'));

        return $pdf->download('ticket-'.$ticket->ticket_number.'.pdf');
    }

    public function scanner()
    {
        $this->ensureScannerAccess();

        return view('admin.tickets.scanner');
    }

    public function scannerLookup(Request $request)
    {
        $this->ensureScannerAccess();

        $payload = trim((string) $request->input('code'));

        $ticketNumber = $this->extractTicketNumber($payload);

        $ticket = Ticket::query()
            ->with('order')
            ->where('ticket_number', $ticketNumber)
            ->first();

        if (! $ticket) {
            return back()->with('error', 'Ticket not found.');
        }

        return view('admin.tickets.scanner', ['ticket' => $ticket, 'lastCode' => $payload]);
    }

    public function scannerStatus(Request $request, Ticket $ticket)
    {
        $this->ensureScannerAccess();

        $data = $request->validate([
            'status' => ['required', 'in:not_checked_in,checked_in,canceled'],
        ]);

        $ticket->update([
            'status' => $data['status'],
            'checked_in_at' => $data['status'] === 'checked_in' ? now() : null,
            'canceled_at' => $data['status'] === 'canceled' ? now() : null,
        ]);

        return back()->with('success', 'Ticket status updated successfully.');
    }

    private function validateTicket(Request $request): array
    {
        return $request->validate([
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:not_checked_in,checked_in,canceled'],
            'holder_name' => ['nullable', 'string', 'max:255'],
            'holder_email' => ['nullable', 'email', 'max:255'],
            'holder_phone' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function extractTicketNumber(string $payload): string
    {
        if (preg_match('/\d{8,}/', $payload, $matches) === 1) {
            return $matches[0];
        }

        return $payload;
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

    private function ensureScannerAccess(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $allowedByRole = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'scanner', 'Admin', 'Scanner', 'Super Admin']);

        $allowedByPermission = method_exists($user, 'can') && $user->can('tickets.manage');

        abort_unless($allowedByRole || $allowedByPermission, 403);
    }
}
