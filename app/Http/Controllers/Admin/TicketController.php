<?php

namespace App\Http\Controllers\Admin;

use App\Mail\AdminTicketIssuedMail;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use App\Models\ScanLog;
use App\Services\Scanner\CheckInService;
use Illuminate\Support\Facades\DB;
use App\Services\UltramsgWhatsappService;
use App\Support\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $managedEvents = $request->user()?->managedEvents ?? collect();
        $managedEventNames = $managedEvents->pluck('name')->all();

        $ticketsQuery = Ticket::query()->with('order')
            ->where(function ($query) {
                $query->whereNull('source')->orWhere('source', '!=', 'guest_list');
            });

        $this->applyManagedEventScopeToTicketsQuery($ticketsQuery, $managedEventNames);

        // Order Assignment: only tickets that belong to orders the user may see.
        if (\App\Orders\OrderAccess::scoped($request->user())) {
            $ticketsQuery->whereHas('order', fn ($o) => $o->visibleTo($request->user()));
        }

        if ($request->filled('status')) {
            $ticketsQuery->where('status', $request->string('status'));
        }

        if ($request->filled('event_name')) {
            $eventName = trim((string) $request->input('event_name'));
            $ticketsQuery->where(function ($query) use ($eventName) {
                $query->where('name', 'like', $eventName.' - %')
                    ->orWhere('name', $eventName);
            });
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
            ->when(! empty($managedEventNames), fn (Builder $query) => $query->whereIn('name', $managedEventNames))
            ->orderBy('name')
            ->pluck('name');

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

    public function show(Request $request, Ticket $ticket)
    {
        $this->guardAssignedTicket($request, $ticket);
        $this->abortIfTicketOutsideManagedEvent($request, $ticket);

        $ticket->load('order');
        $whatsappEnabled = (bool) SystemSettings::get('whatsapp_ticket_sending_enabled', true);

        $scanLogs = ScanLog::query()
            ->with('scannerUser')
            ->where('ticket_id', $ticket->id)
            ->where('action', 'status_update')
            ->latest('scanned_at')
            ->take(30)
            ->get();

        return view('admin.tickets.show', compact('ticket', 'whatsappEnabled', 'scanLogs'));
    }

    public function edit(Request $request, Ticket $ticket)
    {
        $this->guardAssignedTicket($request, $ticket);
        $this->abortIfTicketOutsideManagedEvent($request, $ticket);

        $ticket->load('order');

        return view('admin.tickets.edit', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->guardAssignedTicket($request, $ticket);
        $this->abortIfTicketOutsideManagedEvent($request, $ticket);

        $validated = $this->validateTicket($request);

        // A ticket invalidated by a confirmed refund can never be re-opened from here (nor by the scanner).
        if ($ticket->isRefundCanceled() && $validated['status'] !== 'canceled') {
            return back()->withErrors(['status' => 'This ticket was canceled by a confirmed refund and cannot be reactivated.'])->withInput();
        }

        $ticket->update($validated);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        $this->guardAssignedTicket($request, $ticket);
        $this->abortIfTicketOutsideManagedEvent($request, $ticket);

        if ($ticket->isRefundCanceled()) {
            return back()->with('error', 'A ticket canceled by a refund is part of the financial record and cannot be deleted.');
        }

        $ticket->delete();

        if ($request->input('redirect_to') === 'guest-list') {
            return redirect()->route('admin.guest-list.index')->with('success', 'Ticket deleted successfully.');
        }

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    public function sendEmail(Request $request, Ticket $ticket)
    {
        $this->guardAssignedTicket($request, $ticket);
        $this->abortIfTicketOutsideManagedEvent($request, $ticket);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $ticket->loadMissing('order');
        $qrDataUri = $this->qrDataUri($ticket);
        $event = $this->resolveEvent($ticket->name ?? '');

        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket', 'qrDataUri', 'event'))->output();
        $issuedTicket = IssuedTicket::query()
            ->where('ticket_number', $ticket->ticket_number)
            ->first();

        $showUrl = $issuedTicket
            ? route('front.tickets.show', $issuedTicket)
            : route('front.account.tickets');

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
        $this->guardAssignedTicket($request, $ticket);
        $this->abortIfTicketOutsideManagedEvent($request, $ticket);

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

    public function download(Request $request, Ticket $ticket)
    {
        $this->guardAssignedTicket($request, $ticket);
        $this->abortIfTicketOutsideManagedEvent($request, $ticket);

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

        if (! Auth::attempt(['username' => $data['username'], 'password' => $data['password'], 'is_active' => true])) {
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


    public function scannerLogout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('front.scanner.login');
    }

    public function scanner(Request $request)
    {
        $this->ensureScannerAccess();

        $user = $request->user();
        $context = $this->scannerContext($user);

        $payload = trim((string) $request->input('code'));
        if ($payload === '') {
            return view('admin.tickets.scanner', $context);
        }

        // Legacy deep link (?code=…): read-only look at one ticket, same rules as the JSON lookup.
        $found = app(CheckInService::class)->lookup($user, $payload, $request, null);
        $ticket = in_array($found['code'], ['not_found', 'event_not_allowed'], true) ? null : Ticket::query()->where('ticket_number', $found['ticket']['number'] ?? '')->first();

        return view('admin.tickets.scanner', $context + ['lastCode' => $payload, 'initialResult' => $found, 'ticket' => $ticket]);
    }

    /** Read-only. Never changes a ticket. Used for manual search and to re-verify after an uncertain outcome. */
    public function scannerLookup(Request $request)
    {
        $this->ensureScannerAccess();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:1000'],
            'event_id' => ['nullable', 'integer'],
        ]);

        $found = app(CheckInService::class)->lookup($request->user(), trim($data['code']), $request, isset($data['event_id']) ? (int) $data['event_id'] : null);

        if ($request->expectsJson()) {
            $http = match ($found['result']) {
                CheckInService::NOT_FOUND => 404,
                CheckInService::REJECTED, CheckInService::REVIEW => 409,
                default => 200, // ready / already checked in: information only
            };

            // A lookup never admits anyone: `admit` is always false here; only /scanner/check-in can approve entry.
            return response()->json($found + ['ok' => $http === 200, 'admit' => false], $http);
        }

        return redirect()->route('admin.tickets.scanner', ['code' => trim($data['code'])]);
    }

    /**
     * Atomic admission. One request = one decision, taken by the server inside a transaction with a row lock.
     * The response says "approved" only after the database row was changed by this very request.
     */
    public function scannerCheckIn(Request $request)
    {
        $this->ensureScannerAccess();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:1000'],
            'event_id' => ['nullable', 'integer'],
        ]);

        try {
            $outcome = app(CheckInService::class)->checkIn($request->user(), trim($data['code']), isset($data['event_id']) ? (int) $data['event_id'] : null, $request);
        } catch (\Throwable $e) {
            report($e);

            // Unknown outcome — the client must NOT treat this as approved nor retry blindly; it re-verifies instead.
            return response()->json(['ok' => false, 'result' => 'error', 'code' => 'server_error', 'message' => 'Server error — result unknown. Verify the ticket before deciding.', 'ticket' => null], 500);
        }

        $status = match ($outcome['result']) {
            CheckInService::NOT_FOUND => 404,
            CheckInService::APPROVED => 200,
            default => 409,
        };

        return response()->json($outcome + ['ok' => $outcome['result'] === CheckInService::APPROVED], $status);
    }

    /** Cheap keep-alive: proves the session is still valid and hands back a fresh CSRF token. */
    public function scannerPing(Request $request)
    {
        return response()->json(['ok' => true, 'csrf' => csrf_token(), 'time' => now()->toIso8601String()])->header('Cache-Control', 'no-store');
    }

    public function scannerStatus(Request $request, Ticket $ticket)
    {
        $this->ensureScannerAccess();

        $data = $request->validate([
            'status' => ['required', 'in:not_checked_in,checked_in,canceled'],
        ]);

        // Admission always goes through the atomic service (event scope, refund/cancel, order state, exactly-once).
        if ($data['status'] === 'checked_in') {
            $outcome = app(CheckInService::class)->checkIn($request->user(), (string) $ticket->ticket_number, null, $request);
            $ok = $outcome['result'] === CheckInService::APPROVED;

            if ($request->expectsJson()) {
                return response()->json($outcome + ['ok' => $ok], $ok ? 200 : 409);
            }

            return redirect()->route('admin.tickets.scanner', ['code' => $ticket->ticket_number])->with($ok ? 'success' : 'error', $ok ? 'ENTRY APPROVED — checked in.' : $outcome['message']);
        }

        // Cancelling or re-opening a ticket is an administrative power, never a gate-scanner one.
        $user = $request->user();
        abort_if($user->hasRole('scanner') && $user->roles->count() === 1, 403, 'Scanner staff can only check tickets in.');
        abort_unless($user->can('tickets.update'), 403);
        $this->abortIfTicketOutsideManagedEvent($request, $ticket);

        $previousStatus = null;
        $refusedRefund = false;
        DB::transaction(function () use ($ticket, $data, $request, &$previousStatus, &$refusedRefund) {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($locked->isRefundCanceled()) {
                $refusedRefund = true;

                return;
            }
            $previousStatus = $locked->status;
            $locked->update([
                'status' => $data['status'],
                'checked_in_at' => null,
                'canceled_at' => $data['status'] === 'canceled' ? now() : null,
            ]);
            ScanLog::create([
                'ticket_id' => $locked->id, 'event_id' => $locked->event_id, 'event_name' => $locked->eventLabel(), 'ticket_number' => $locked->ticket_number,
                'action' => 'status_update', 'previous_status' => $previousStatus, 'new_status' => $data['status'], 'payload' => $locked->ticket_number,
                'scanned_by_user_id' => auth()->id(), 'scanner_name' => auth()->user()?->name, 'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(), 'scanned_at' => now(),
            ]);
        });

        if ($refusedRefund) {
            $ticket->refresh();
            $this->logRefundRejection($request, $ticket, (string) $ticket->ticket_number, 'status_update_rejected_refunded');

            return redirect()->route('admin.tickets.scanner', ['code' => $ticket->ticket_number])->with('error', 'REFUNDED / CANCELED — '.$ticket->refundCancellationText().'. Entry is not allowed.');
        }

        return redirect()->route('admin.tickets.scanner', ['code' => $ticket->ticket_number])->with('success', 'Ticket status updated successfully.');
    }

    /** What the scanner screen needs: who is working, which event(s) they may scan, and how many they have admitted today. */
    private function scannerContext($user): array
    {
        $assigned = $user->managedEvents()->get(['events.id', 'events.name', 'events.event_date']);
        $events = $assigned->isNotEmpty()
            ? $assigned
            : Event::query()->whereNull('archived_at')->orderByDesc('event_date')->limit(30)->get(['id', 'name', 'event_date']);

        return [
            'scannerEvents' => $events->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])->values(),
            'scannerRestricted' => $assigned->isNotEmpty(),
            'scannerCheckins' => ScanLog::query()->where('scanned_by_user_id', $user->id)->where('action', 'status_update')->where('new_status', 'checked_in')->where('scanned_at', '>=', now()->startOfDay())->count(),
            'canUndo' => ! ($user->hasRole('scanner') && $user->roles->count() === 1) && $user->can('tickets.update'),
        ];
    }


    /** Audit trail of a refused scan. Uses its own action names so it never counts as a successful scan or check-in. */
    private function logRefundRejection(Request $request, Ticket $ticket, string $payload, string $action): void
    {
        ScanLog::create([
            'ticket_id' => $ticket->id,
            'event_id' => $ticket->event_id,
            'event_name' => $ticket->eventLabel(),
            'ticket_number' => $ticket->ticket_number,
            'action' => $action,
            'previous_status' => $ticket->status,
            'new_status' => $ticket->status,
            'payload' => $payload,
            'scanned_by_user_id' => auth()->id(),
            'scanner_name' => auth()->user()?->name,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'scanned_at' => now(),
        ]);
    }

    private function applyManagedEventScopeToTicketsQuery(Builder $query, array $eventNames): void
    {
        if (empty($eventNames)) {
            return;
        }

        $query->where(function (Builder $ticketQuery) use ($eventNames) {
            foreach ($eventNames as $name) {
                $ticketQuery->orWhere('name', 'like', $name.' - %')
                    ->orWhere('name', $name);
            }
        });
    }

    private function abortIfTicketOutsideManagedEvent(Request $request, Ticket $ticket): void
    {
        $managedEventNames = $request->user()?->managedEvents->pluck('name')->all() ?? [];
        if (empty($managedEventNames)) {
            return;
        }

        $ticketName = (string) ($ticket->name ?? '');
        $matches = collect($managedEventNames)->contains(
            fn ($name) => $ticketName === $name || str_starts_with($ticketName, $name.' - ')
        );

        abort_unless($matches, 403);
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

    /** Order Assignment: a ticket is reachable only through an order the user may see (404 otherwise). */
    private function guardAssignedTicket(Request $request, Ticket $ticket): void
    {
        // A guest-list ticket belongs to the person who added it (Super Admin sees all); it has no order to check.
        if ($ticket->source === 'guest_list') {
            abort_unless($ticket->isGuestVisibleTo($request->user()), 404);

            return;
        }

        if (! \App\Orders\OrderAccess::scoped($request->user())) {
            return;
        }

        $ticket->loadMissing('order');
        abort_unless($ticket->order && $ticket->order->isVisibleTo($request->user()), 404);
    }
}
