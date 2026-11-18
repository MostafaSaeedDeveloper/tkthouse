<?php

namespace App\Console\Commands;

use App\Mail\HolderTicketsIssuedMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\IssuedTicket;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * One-off recovery: paid orders whose ticket e-mail went out without the PDF (or never arrived) get the PDF e-mail again.
 * Dry run by default: it only lists who would be e-mailed. Add --send to actually send. Each order is marked once re-sent,
 * so running it again never e-mails the same order twice (use --force to override).
 */
class ResendTicketPdfs extends Command
{
    protected $signature = 'orders:resend-ticket-pdfs
        {--since= : Only paid orders paid on/after this date-time (required, e.g. "2026-11-17 00:00")}
        {--until= : Only paid orders paid before this date-time}
        {--event= : Only orders containing this event id}
        {--order=* : Only these order ids}
        {--send : Actually send (without it nothing is sent)}
        {--force : Also re-send orders already re-sent by this command}
        {--delay=300 : Milliseconds to wait between orders (mail rate limits)}';

    protected $description = 'Re-send the tickets PDF e-mail to customers/holders of paid orders (dry run unless --send).';

    private const MARK = 'Tickets PDF re-sent';

    public function handle(): int
    {
        if (! $this->option('since') && ! $this->option('order')) {
            $this->error('Pass --since="YYYY-MM-DD HH:MM" (or --order=ID) so the scope is explicit.');

            return self::INVALID;
        }

        $send = (bool) $this->option('send');
        $query = Order::query()->where('status', 'paid')->whereHas('issuedTickets')->with(['customer', 'issuedTickets'])->orderBy('id');
        if ($this->option('since')) {
            $query->where('paid_at', '>=', Carbon::parse($this->option('since')));
        }
        if ($this->option('until')) {
            $query->where('paid_at', '<', Carbon::parse($this->option('until')));
        }
        if ($this->option('order')) {
            $query->whereIn('id', $this->option('order'));
        }
        if ($this->option('event')) {
            $query->whereHas('items', fn ($q) => $q->where('event_id', $this->option('event')));
        }

        $rows = [];
        $sent = $failed = $skipped = 0;

        $query->each(function (Order $order) use ($send, &$rows, &$sent, &$failed, &$skipped) {
            $already = \Spatie\Activitylog\Models\Activity::query()->where('subject_type', Order::class)->where('subject_id', $order->id)
                ->where('log_name', 'order_delivery')->where('description', self::MARK)->exists();
            if ($already && ! $this->option('force')) {
                $skipped++;

                return;
            }

            $customer = trim((string) $order->customer?->email);
            $holders = $order->issuedTickets->filter(fn (IssuedTicket $t) => filled($t->holder_email))
                ->groupBy(fn (IssuedTicket $t) => mb_strtolower(trim((string) $t->holder_email)));
            $to = array_filter(array_unique([mb_strtolower($customer), ...$holders->keys()->all()]));
            $rows[] = [$order->id, $order->order_number, $order->paid_at?->format('Y-m-d H:i'), implode(', ', $to) ?: '(no e-mail)'];

            if (! $send) {
                return;
            }

            $ok = true;
            try {
                if ($customer !== '') {
                    Mail::to($customer)->send(new OrderTicketsIssuedMail($order));
                }
                foreach ($holders as $email => $tickets) {
                    if ($email === mb_strtolower($customer)) {
                        continue; // the customer mail already carries all the tickets
                    }
                    Mail::to($email)->send(new HolderTicketsIssuedMail($order, $tickets->values(), $email));
                }
            } catch (\Throwable $e) {
                $ok = false;
                report($e);
            }

            if ($ok) {
                $sent++;
                activity('order_delivery')->performedOn($order)->log(self::MARK);
            } else {
                $failed++;
            }
            usleep(max(0, (int) $this->option('delay')) * 1000);
        });

        $this->table(['Order id', 'Order #', 'Paid at', 'E-mail to'], $rows);
        $this->info($send
            ? "Re-sent: {$sent}, failed: {$failed}, already re-sent (skipped): {$skipped}."
            : 'Dry run: '.count($rows)." orders would be e-mailed ({$skipped} already re-sent, skipped). Add --send to send.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
