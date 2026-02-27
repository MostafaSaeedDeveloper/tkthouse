<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::query()->with('order')->latest()->paginate(15);

        return view('admin.tickets.index', compact('tickets'));
    }

    public function create()
    {
        return view('admin.tickets.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateTicket($request);
        Ticket::create($validated);

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

        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket'))->output();
        $showUrl = route('admin.tickets.show', $ticket);

        Mail::raw(
            "Your ticket {$ticket->ticket_number} is attached as PDF.\n\nView ticket: {$showUrl}",
            static function ($message) use ($data, $pdf, $ticket) {
                $message->to($data['email'])
                    ->subject('Your Ticket #'.$ticket->ticket_number)
                    ->attachData($pdf, 'ticket-'.$ticket->ticket_number.'.pdf', ['mime' => 'application/pdf']);
            }
        );

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
        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket'));

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
            'name' => ['required', 'string', 'max:255'],
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

    private function ensureScannerAccess(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $allowed = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['Scanner', 'Admin', 'Super Admin']);

        abort_unless($allowed, 403);
    }
}
