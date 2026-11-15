<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\IssuedTicket;
use App\Services\Tickets\QrReleaseService;
use Illuminate\Console\Command;

/** Every minute: for LIVE events that have tickets waiting for their reveal (or pending e-mails), mark what became eligible and queue the batches. Sends nothing itself. */
class ReleaseQrNotifications extends Command
{
    protected $signature = 'tickets:release-qr';
    protected $description = 'Queue QR-release e-mails for live events whose QR has just become visible';

    public function handle(QrReleaseService $service): int
    {
        Event::query()->where('qr_release_mode', Event::RELEASE_LIVE)
            ->whereExists(fn ($q) => $q->from('issued_tickets')->whereColumn('issued_tickets.event_id', 'events.id')->where(function ($w) {
                $w->where('qr_notice_status', IssuedTicket::NOTICE_PENDING)
                    ->orWhere(fn ($x) => $x->where('qr_notice_status', IssuedTicket::NOTICE_NOT_ELIGIBLE)->where('qr_locked_at_issue', true)->whereNull('qr_notice_skipped_at'));
            }))
            ->each(fn (Event $event) => $service->process($event));

        return self::SUCCESS;
    }
}
