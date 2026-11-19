<?php

namespace App\Console\Commands;

use App\Mail\GuestInvitationMail;
use App\Models\Event;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

/**
 * Re-sends the guest-list invitation (ticket PDF attached) to guests who have an e-mail.
 * Dry run unless --send. Each ticket is logged once re-sent so a second run never e-mails the same guest twice (--force overrides).
 */
class ResendGuestTicketPdfs extends Command
{
    protected $signature = 'tickets:resend-guest-pdfs
        {--since= : Only guest tickets issued on/after this date-time (required, e.g. "2026-11-17 00:00")}
        {--until= : Only guest tickets issued before this date-time}
        {--event= : Only this event id}
        {--ticket=* : Only these ticket ids}
        {--send : Actually send (without it nothing is sent)}
        {--force : Also re-send tickets already re-sent by this command}
        {--delay=300 : Milliseconds to wait between e-mails}';

    protected $description = 'Re-send the guest-list ticket PDF e-mail (dry run unless --send).';

    private const MARK = 'Guest ticket PDF re-sent';

    public function handle(): int
    {
        if (! $this->option('since') && ! $this->option('ticket')) {
            $this->error('Pass --since="YYYY-MM-DD HH:MM" (or --ticket=ID) so the scope is explicit.');

            return self::INVALID;
        }

        $send = (bool) $this->option('send');
        $query = Ticket::query()->where('source', 'guest_list')->whereNotNull('holder_email')->where('holder_email', '!=', '')->orderBy('id');
        if ($this->option('since')) {
            $query->where('issued_at', '>=', Carbon::parse($this->option('since')));
        }
        if ($this->option('until')) {
            $query->where('issued_at', '<', Carbon::parse($this->option('until')));
        }
        if ($this->option('event')) {
            $query->where('event_id', $this->option('event'));
        }
        if ($this->option('ticket')) {
            $query->whereIn('id', $this->option('ticket'));
        }

        $rows = [];
        $sent = $failed = $skipped = 0;

        $query->each(function (Ticket $ticket) use ($send, &$rows, &$sent, &$failed, &$skipped) {
            $already = Activity::query()->where('subject_type', Ticket::class)->where('subject_id', $ticket->id)
                ->where('log_name', 'tickets')->where('description', self::MARK)->exists();
            if ($already && ! $this->option('force')) {
                $skipped++;

                return;
            }

            $rows[] = [$ticket->id, $ticket->ticket_number, $ticket->holder_name, $ticket->issued_at?->format('Y-m-d H:i'), $ticket->holder_email];
            if (! $send) {
                return;
            }

            try {
                $pdf = Pdf::loadView('admin.tickets.pdf', [
                    'ticket' => $ticket,
                    'qrDataUri' => $this->qrDataUri($ticket),
                    'event' => Event::query()->where('name', $ticket->eventLabel())->first(),
                ])->output();
                Mail::to($ticket->holder_email)->send(new GuestInvitationMail(ticket: $ticket, pdfBinary: $pdf));
                activity('tickets')->performedOn($ticket)->log(self::MARK);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                report($e);
            }
            usleep(max(0, (int) $this->option('delay')) * 1000);
        });

        $this->table(['Ticket id', 'Ticket #', 'Guest', 'Issued at', 'E-mail to'], $rows);
        $this->info($send
            ? "Re-sent: {$sent}, failed: {$failed}, already re-sent (skipped): {$skipped}."
            : 'Dry run: '.count($rows)." guest tickets would be e-mailed ({$skipped} already re-sent, skipped). Add --send to send.");

        return $failed ? self::FAILURE : self::SUCCESS;
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
            // fall back to the URL
        }

        return $url;
    }
}
