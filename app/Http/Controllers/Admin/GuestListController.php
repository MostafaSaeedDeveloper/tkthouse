<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminTicketIssuedMail;
use App\Models\Event;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class GuestListController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::query()->guestList()->with('event');

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->input('event_id'));
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === 'sent') {
                $query->whereNotNull('invitation_sent_at');
            }
            if ($status === 'not_sent') {
                $query->whereNull('invitation_sent_at');
            }
            if ($status === 'scanned') {
                $query->where('status', 'checked_in');
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('holder_name', 'like', "%{$search}%")
                    ->orWhere('holder_email', 'like', "%{$search}%")
                    ->orWhere('holder_phone', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        return view('admin.guest-lists.index', [
            'guests' => $query->latest()->paginate(20)->withQueryString(),
            'events' => Event::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('admin.guest-lists.create', [
            'events' => Event::query()
                ->with(['tickets:id,event_id,name'])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'guest_type' => ['required', 'string', 'max:255'],
            'guests' => ['required', 'array', 'min:1'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:255'],
        ]);

        $event = Event::query()->with('tickets:id,event_id,name')->findOrFail((int) $data['event_id']);
        $guestTypeLabel = $this->resolveGuestTypeLabel((string) $data['guest_type'], $event);

        foreach ($data['guests'] as $guest) {
            $ticketNumber = $this->generateTicketNumber();

            $ticket = Ticket::create([
                'event_id' => $event->id,
                'name' => $event->name.' - '.$guestTypeLabel,
                'description' => 'Guest list invitation',
                'price' => 0,
                'status' => 'not_checked_in',
                'ticket_source' => 'guest_list',
                'guest_category' => $guestTypeLabel,
                'holder_name' => $guest['name'],
                'holder_email' => $guest['email'] ?? null,
                'holder_phone' => $guest['phone'] ?? null,
                'ticket_number' => $ticketNumber,
                'qr_payload' => $ticketNumber,
                'issued_at' => now(),
            ]);

            if (! empty($guest['email'])) {
                $this->sendInvitationEmail($ticket, $guest['email']);
            }
        }

        return redirect()->route('admin.guest-lists.index')->with('success', 'Guest list invitations created successfully.');
    }

    public function edit(Ticket $guest_list)
    {
        abort_unless($guest_list->ticket_source === 'guest_list', 404);

        return view('admin.guest-lists.edit', [
            'guest' => $guest_list,
            'events' => Event::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Ticket $guest_list)
    {
        abort_unless($guest_list->ticket_source === 'guest_list', 404);

        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:not_checked_in,checked_in,canceled'],
        ]);

        $event = Event::query()->findOrFail((int) $data['event_id']);
        $ticketType = trim((string) ($data['type'] ?? ''));
        $guestTypeLabel = $ticketType === '' ? 'Guest Regular' : $this->normalizeGuestType($ticketType);

        $guest_list->update([
            'event_id' => $event->id,
            'name' => $event->name.' - '.$guestTypeLabel,
            'holder_name' => $data['name'],
            'holder_email' => $data['email'] ?? null,
            'holder_phone' => $data['phone'] ?? null,
            'guest_category' => $guestTypeLabel,
            'status' => $data['status'],
        ]);

        return redirect()->route('admin.guest-lists.index')->with('success', 'Guest updated successfully.');
    }

    public function destroy(Ticket $guest_list)
    {
        abort_unless($guest_list->ticket_source === 'guest_list', 404);
        $guest_list->delete();

        return back()->with('success', 'Guest invitation deleted successfully.');
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'guest_type' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
        ]);

        $event = Event::query()->with('tickets:id,event_id,name')->findOrFail((int) $data['event_id']);
        $guestTypeLabel = $this->resolveGuestTypeLabel((string) $data['guest_type'], $event);

        $rows = $this->parseImportRows($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension());

        return back()->withInput()->with('imported_guests', $rows)
            ->with('import_event_id', (int) $data['event_id'])
            ->with('import_guest_type', $guestTypeLabel);
    }

    public function export(Request $request)
    {
        $request->validate(['event_id' => ['required', 'exists:events,id']]);

        $event = Event::query()->findOrFail((int) $request->input('event_id'));
        $guests = Ticket::query()->guestList()->where('event_id', $event->id)->orderBy('holder_name')->get();

        $filename = 'guest-list-'.$event->id.'-'.now()->format('YmdHis').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($guests) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Phone', 'Type', 'Ticket Number', 'Invitation Status', 'Scan Status']);
            foreach ($guests as $guest) {
                fputcsv($out, [
                    $guest->holder_name,
                    $guest->holder_email,
                    $guest->holder_phone,
                    $guest->guest_category,
                    $guest->ticket_number,
                    $guest->invitation_sent_at ? 'sent' : 'not_sent',
                    $guest->status,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function parseImportRows(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        return $extension === 'xlsx'
            ? $this->parseXlsxRows($path)
            : $this->parseCsvRows($path);
    }

    private function parseCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $header = fgetcsv($handle) ?: [];
        $mapping = $this->resolveColumnMapping($header);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $guest = $this->mapGuestFromRow($line, $mapping);
            if (! empty($guest['name'])) {
                $rows[] = $guest;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function parseXlsxRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared) {
                foreach ($shared->si as $si) {
                    $sharedStrings[] = trim((string) ($si->t ?? ''));
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (! $sheetXml) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet) {
            return [];
        }

        $rows = [];
        $header = [];
        $mapping = [];
        foreach ($sheet->sheetData->row as $rowIndex => $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/([A-Z]+)/', $ref, $matches);
                $col = $matches[1] ?? 'A';

                $value = '';
                if ((string) $cell['t'] === 's') {
                    $idx = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$idx] ?? '';
                } else {
                    $value = (string) ($cell->v ?? '');
                }
                $line[$col] = trim($value);
            }

            if ((int) $rowIndex === 0) {
                ksort($line);
                $header = array_values($line);
                $mapping = $this->resolveColumnMapping($header);
                continue;
            }

            ksort($line);
            $guest = $this->mapGuestFromRow(array_values($line), $mapping);
            if (! empty($guest['name'])) {
                $rows[] = $guest;
            }
        }

        return $rows;
    }

    private function resolveColumnMapping(array $header): array
    {
        $mapping = ['name' => 0, 'email' => null, 'phone' => null, 'type' => null];

        foreach ($header as $index => $column) {
            $key = strtolower(trim((string) $column));
            if ($key === '') {
                continue;
            }

            if (str_contains($key, 'name') && $mapping['name'] === 0) {
                $mapping['name'] = $index;
                continue;
            }
            if (str_contains($key, 'mail')) {
                $mapping['email'] = $index;
                continue;
            }
            if (str_contains($key, 'phone') || str_contains($key, 'mobile')) {
                $mapping['phone'] = $index;
                continue;
            }
            if (str_contains($key, 'type') || str_contains($key, 'category')) {
                $mapping['type'] = $index;
            }
        }

        return $mapping;
    }

    private function mapGuestFromRow(array $row, array $mapping): array
    {
        return [
            'name' => trim((string) ($row[$mapping['name']] ?? '')),
            'email' => trim((string) ($mapping['email'] !== null ? ($row[$mapping['email']] ?? '') : '')),
            'phone' => trim((string) ($mapping['phone'] !== null ? ($row[$mapping['phone']] ?? '') : '')),
            'type' => trim((string) ($mapping['type'] !== null ? ($row[$mapping['type']] ?? '') : '')),
        ];
    }

    private function sendInvitationEmail(Ticket $ticket, string $email): void
    {
        $pdf = Pdf::loadView('admin.tickets.pdf', [
            'ticket' => $ticket,
            'qrDataUri' => 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode((string) $ticket->qr_payload),
            'event' => $ticket->event,
        ])->output();

        Mail::to($email)->send(new AdminTicketIssuedMail(
            ticket: $ticket,
            showUrl: route('admin.tickets.show', $ticket),
            recipientEmail: $email,
            pdfBinary: $pdf,
        ));

        $ticket->forceFill(['invitation_sent_at' => now()])->save();
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = now()->format('ymd').str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Ticket::query()->where('ticket_number', $number)->exists());

        return $number;
    }

    private function resolveGuestTypeLabel(string $requestedType, Event $event): string
    {
        $ticketType = trim($requestedType);

        if ($ticketType === '') {
            throw ValidationException::withMessages([
                'guest_type' => 'Guest type is required.',
            ]);
        }

        $eventTypes = $event->tickets
            ->pluck('name')
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values();

        if ($eventTypes->isNotEmpty() && ! $eventTypes->contains($ticketType)) {
            throw ValidationException::withMessages([
                'guest_type' => 'Selected guest type must match one of the event ticket types.',
            ]);
        }

        return $this->normalizeGuestType($ticketType);
    }

    private function normalizeGuestType(string $type): string
    {
        $clean = trim($type);
        $clean = preg_replace('/^guest\s+/i', '', $clean) ?? $clean;

        return 'Guest '.$clean;
    }
}
