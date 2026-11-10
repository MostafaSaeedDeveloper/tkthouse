<?php

namespace App\Services\Invoices;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Services\Checkout\CheckoutPricing;
use App\Support\OrderPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A payment invoice is a read-only view of an order that was actually paid: nothing is stored, nothing is recalculated.
 * Every amount comes from the order itself (subtotal, discount, total) and from its confirmed refunds, so old and new orders
 * are handled the same way, and a refund never replaces the original invoice - it is shown next to it.
 * Anything we do not have (e.g. a payment date on a very old order) is left out instead of being invented.
 */
final class Invoice
{
    private function __construct(
        public readonly Order $order,
        public readonly string $number,
        public readonly string $orderNumber,
        public readonly ?\Illuminate\Support\Carbon $paidAt,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly ?string $customerPhone,
        public readonly array $event,
        public readonly Collection $lines,
        public readonly int $ticketCount,
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly ?string $promoCode,
        public readonly array $fees,
        public readonly float $adjustment,
        public readonly float $total,
        public readonly string $paymentMethod,
        public readonly ?string $paymentReference,
        public readonly Collection $refunds,
        public readonly float $refunded,
        public readonly string $currency = 'EGP',
    ) {}

    /** An invoice exists for payments that really happened: paid orders, and orders a confirmed refund moved to (partially) refunded. */
    public static function isAvailableFor(Order $order): bool
    {
        if ($order->status === 'paid') {
            return true;
        }

        return in_array($order->status, Order::REFUND_STATUSES, true)
            && $order->refunds()->where('status', PaymentRefund::SUCCEEDED)->exists();
    }

    public static function numberFor(Order $order): string
    {
        return 'INV-'.OrderPresenter::number($order);
    }

    public static function make(Order $order): self
    {
        $order->loadMissing(['customer', 'items.event.fees', 'items.eventTicket']);

        $attempts = $order->paymentAttempts()->orderByDesc('id')->get();
        $paidAttempt = $attempts->firstWhere('status', PaymentAttempt::PAID);

        // Subtotal: the stored one; orders from before it existed fall back to the sum of their lines.
        $lineSum = round((float) $order->items->sum('line_total'), 2);
        $subtotal = (float) $order->subtotal_amount > 0 ? round((float) $order->subtotal_amount, 2) : $lineSum;
        $discount = round(max(0, (float) $order->discount_amount), 2);
        $total = round((float) $order->total_amount, 2);
        $extra = round($total - ($subtotal - $discount), 2);   // what was charged on top of the discounted tickets

        [$fees, $adjustment] = self::splitExtra($order, $subtotal, $extra);

        $refunds = $order->refunds()->where('status', PaymentRefund::SUCCEEDED)->orderBy('completed_at')->orderBy('id')->get()
            ->map(fn (PaymentRefund $r) => [
                'amount' => round($r->amount_minor / 100, 2),
                'date' => $r->completed_at ?? $r->confirmed_at ?? $r->refunded_at,
                'reference' => $r->internal_reference,
                'full' => $r->isFull(),
            ]);

        $customer = $order->customer;

        return new self(
            order: $order,
            number: self::numberFor($order),
            orderNumber: OrderPresenter::number($order),
            paidAt: $order->paid_at ?? $paidAttempt?->paid_at ?? $paidAttempt?->settled_at,
            customerName: trim((string) $customer?->full_name) ?: 'Customer',
            customerEmail: (string) $customer?->email,
            customerPhone: filled($customer?->phone) ? (string) $customer->phone : null,
            event: self::eventInfo($order),
            lines: self::lines($order),
            ticketCount: OrderPresenter::ticketCount($order),
            subtotal: $subtotal,
            discount: $discount,
            promoCode: filled($order->promo_code) ? strtoupper((string) $order->promo_code) : null,
            fees: $fees,
            adjustment: $adjustment,
            total: $total,
            paymentMethod: self::methodLabel($order),
            paymentReference: $paidAttempt?->internal_reference,
            refunds: $refunds,
            refunded: round($refunds->sum('amount'), 2),
        );
    }

    /**
     * The extra charge is itemised only when today's event fees reproduce it exactly; otherwise it is one honest line
     * (fees may have been edited since the order was placed, and we never guess a breakdown).
     *
     * @return array{0: list<array{name: string, detail: ?string, amount: float}>, 1: float} [fee lines, unexplained adjustment]
     */
    private static function splitExtra(Order $order, float $subtotal, float $extra): array
    {
        if (abs($extra) < 0.01) {
            return [[], 0.0];
        }

        $event = $order->items->pluck('event')->filter()->first();
        if ($extra > 0 && $event) {
            $fees = collect(app(CheckoutPricing::class)->fees($event, $subtotal));
            if ($fees->isNotEmpty() && abs(round($fees->sum('amount'), 2) - $extra) < 0.01) {
                return [$fees->map(fn ($f) => [
                    'name' => $f['name'],
                    'detail' => $f['fee_type'] === 'percentage' ? rtrim(rtrim(number_format($f['value'], 2), '0'), '.').'% of tickets' : null,
                    'amount' => $f['amount'],
                ])->all(), 0.0];
            }
        }

        return $extra > 0
            ? [[['name' => 'Service fees & taxes', 'detail' => null, 'amount' => $extra]], 0.0]
            : [[], $extra];
    }

    private static function eventInfo(Order $order): array
    {
        $event = $order->items->pluck('event')->filter()->first();

        $time = null;
        if ($event?->event_time) {
            try {
                $time = \Illuminate\Support\Carbon::parse($event->event_time)->format('g:i A');
            } catch (\Throwable) {
                $time = null;
            }
        }

        return [
            'name' => $event ? trim((string) $event->name) : (OrderPresenter::eventNames($order)->implode(' · ') ?: 'Event'),
            'date' => $event?->event_date?->format('D, d M Y'),
            'time' => $time,
            'location' => filled($event?->location) ? trim((string) $event->location) : null,
        ];
    }

    /** @return Collection<int, array{name: string, qty: int, price: float, total: float}> */
    private static function lines(Order $order): Collection
    {
        return $order->items
            ->groupBy(function ($item) {
                $name = trim((string) $item->ticket_name);
                $prefix = trim((string) $item->event?->name).' - ';
                if ($prefix !== ' - ' && Str::startsWith($name, $prefix)) {
                    $name = trim(Str::after($name, $prefix));
                }

                return $name.'|'.number_format((float) $item->ticket_price, 2, '.', '');
            })
            ->map(fn ($group, $key) => [
                'name' => Str::beforeLast($key, '|') ?: 'Ticket',
                'qty' => (int) $group->sum(fn ($i) => max(1, (int) $i->quantity)),
                'price' => round((float) $group->first()->ticket_price, 2),
                'total' => round((float) $group->sum('line_total'), 2),
            ])
            ->values();
    }

    private static function methodLabel(Order $order): string
    {
        $code = (string) $order->payment_method;
        if ($code === '') {
            return 'Online payment';
        }

        $method = PaymentMethod::query()->where('code', $code)->first();
        $label = trim((string) ($method?->checkout_label ?: $method?->name));

        return $label !== '' ? $label : ucwords(str_replace('_', ' ', $code));
    }

    /* ------------------------------------------------------------ refund state */

    public function isRefunded(): bool
    {
        return $this->refunded > 0;
    }

    public function isFullyRefunded(): bool
    {
        return $this->refunded > 0 && $this->refunded + 0.009 >= $this->total;
    }

    public function netPaid(): float
    {
        return round(max(0, $this->total - $this->refunded), 2);
    }

    public function money(float $amount): string
    {
        return number_format($amount, 2).' '.$this->currency;
    }

    /* ------------------------------------------------------------ output */

    public function filename(): string
    {
        return 'invoice-'.$this->orderNumber.'.pdf';
    }

    public function pdf(): string
    {
        return Pdf::loadView('invoices.pdf', ['i' => $this])->setPaper('a4')->output();
    }
}
