<?php

namespace App\Http\Controllers\Admin;

use App\Mail\AdminTicketIssuedMail;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use App\Models\ScanLog;
use App\Services\UltramsgWhatsappService;
use App\Support\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $ticketsQuery = Ticket::query()->with('order')
            ->where(function ($query) {
                $query->whereNull('source')->orWhere('source', '!=', 'guest_list');
            });

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

        $ticketNumber = $this->generateTicketNumber();

        Ticket::create($validated + [
            'name' => $validated['holder_name'] ?? 'Ticket',
            'source' => 'standard',
            'ticket_number' => $ticketNumber,
            'qr_payload' => $ticketNumber,
            'issued_at' => now(),
        ]);

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('order');
        $whatsappEnabled = (bool) SystemSettings::get('whatsapp_ticket_sending_enabled', true);

        return view('admin.tickets.show', compact('ticket', 'whatsappEnabled'));
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

    public function destroy(Request $request, Ticket $ticket)
    {
        $ticket->delete();

        if ($request->input('redirect_to') === 'guest-list') {
            return redirect()->route('admin.guest-list.index')->with('success', 'Ticket deleted successfully.');
        }

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    public function sendEmail(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $ticket->loadMissing('order');
        $qrDataUri = $this->qrDataUri($ticket);
        $event = $this->resolveEvent($ticket->name ?? '');

        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket', 'qrDataUri', 'event'))->output();
        $showUrl = route('admin.tickets.show', $ticket);

        Mail::to($data['email'])->send(new AdminTicketIssuedMail(
            ticket: $ticket,
            showUrl: $showUrl,
            recipientEmail: $data['email'],
            pdfBinary: $pdf,
        ));

        return back()->with('success', 'Ticket sent by email successfully.');
    }

    public function sendWhatsapp(Request $request, Ticket $ticket, UltramsgWhatsappService $whatsappService)
    {
        if (! (bool) SystemSettings::get('whatsapp_ticket_sending_enabled', true)) {
            return back()->with('error', 'WhatsApp ticket sending is disabled from settings.');
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:255'],
        ]);

        try {
            $sent = $whatsappService->sendTicket($ticket, $data['phone']);

            if (! $sent) {
                return back()->with('error', 'Ticket phone is missing or invalid.');
            }

            return back()->with('success', 'Ticket sent by WhatsApp successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Unable to send WhatsApp message right now.');
        }
    }

    public function download(Ticket $ticket)
    {
        $ticket->loadMissing('order');
        $qrDataUri = $this->qrDataUri($ticket);
        $event = $this->resolveEvent($ticket->name ?? '');

        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket', 'qrDataUri', 'event'));

        return $pdf->download('ticket-'.$ticket->ticket_number.'.pdf');
    }


    public function scannerLogin()
    {
        if (auth()->check() && auth()->user()?->can('scanner.access')) {
            return redirect()->route('admin.tickets.scanner');
        }

        return view('admin.tickets.scanner-login');
    }

    public function scannerLoginSubmit(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['username' => $data['username'], 'password' => $data['password']])) {
            return back()->withErrors(['username' => 'Invalid credentials.'])->withInput();
        }

        $request->session()->regenerate();

        abort_unless(auth()->user()?->can('scanner.access') || auth()->user()?->hasRole('scanner'), 403);

        auth()->user()?->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        ScanLog::create([
            'action' => 'scanner_login',
            'scanned_by_user_id' => auth()->id(),
            'scanner_name' => auth()->user()?->name,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'scanned_at' => now(),
        ]);

        return redirect()->route('admin.tickets.scanner');
    }

    public function scanner(Request $request)
    {
        $this->ensureScannerAccess();

        $payload = trim((string) $request->input('code'));
        if ($payload === '') {
            return view('admin.tickets.scanner');
        }

        $ticket = Ticket::query()
            ->with('order')
            ->where('ticket_number', $this->extractTicketNumber($payload))
            ->first();

        if (! $ticket) {
            return view('admin.tickets.scanner', ['lastCode' => $payload])->with('error', 'Ticket not found.');
        }

        return view('admin.tickets.scanner', ['ticket' => $ticket, 'lastCode' => $payload]);
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
            ScanLog::create([
                'ticket_number' => $ticketNumber,
                'action' => 'lookup_failed',
                'payload' => $payload,
                'scanned_by_user_id' => auth()->id(),
                'scanner_name' => auth()->user()?->name,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'scanned_at' => now(),
            ]);

            return back()->with('error', 'Ticket not found.');
        }

        ScanLog::create([
            'ticket_id' => $ticket->id,
            'event_name' => $ticket->eventLabel(),
            'ticket_number' => $ticket->ticket_number,
            'action' => 'lookup_success',
            'previous_status' => $ticket->status,
            'new_status' => $ticket->status,
            'payload' => $payload,
            'scanned_by_user_id' => auth()->id(),
            'scanner_name' => auth()->user()?->name,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'scanned_at' => now(),
        ]);

        return view('admin.tickets.scanner', ['ticket' => $ticket, 'lastCode' => $payload]);
    }

    public function scannerStatus(Request $request, Ticket $ticket)
    {
        $this->ensureScannerAccess();

        $data = $request->validate([
            'status' => ['required', 'in:not_checked_in,checked_in,canceled'],
        ]);

        $previousStatus = $ticket->status;

        $ticket->update([
            'status' => $data['status'],
            'checked_in_at' => $data['status'] === 'checked_in' ? now() : null,
            'canceled_at' => $data['status'] === 'canceled' ? now() : null,
        ]);

        ScanLog::create([
            'ticket_id' => $ticket->id,
            'event_name' => $ticket->eventLabel(),
            'ticket_number' => $ticket->ticket_number,
            'action' => 'status_update',
            'previous_status' => $previousStatus,
            'new_status' => $data['status'],
            'payload' => $ticket->ticket_number,
            'scanned_by_user_id' => auth()->id(),
            'scanner_name' => auth()->user()?->name,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'scanned_at' => now(),
        ]);

        return redirect()->route('admin.tickets.scanner', ['code' => $ticket->ticket_number])->with('success', 'Ticket status updated successfully.');
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


    private function generateTicketNumber(): string
    {
        do {
            $ticketNumber = (string) random_int(1000000000, 9999999999);
        } while (Ticket::query()->where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
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

        $allowedByPermission = method_exists($user, 'can')
            && ($user->can('tickets.update') || $user->can('scanner.access'));

        abort_unless($allowedByRole || $allowedByPermission, 403);
    }

    private function resolveEvent(string $ticketName): ?Event
    {
        $candidates = collect([
            trim((string) Str::beforeLast($ticketName, ' - ')),
            trim((string) Str::before($ticketName, ' - ')),
            trim($ticketName),
        ])->filter()->unique()->values();

        foreach ($candidates as $eventName) {
            $event = Event::query()
                ->with('images')
                ->where('name', $eventName)
                ->first();

            if ($event) {
                return $event;
            }
        }

        return null;
    }
}
