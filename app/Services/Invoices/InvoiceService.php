<?php

namespace App\Services\Invoices;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Checkout\CheckoutPricing;
use App\Support\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Paid-order invoices. The invoice is a snapshot written once (number, date, seller, fee lines) the first time it is
 * needed, so later changes to the event or settings never rewrite an invoice a customer already received.
 * Taxes and fees are not invented here: they are the event's own fees, as charged at checkout.
 */
class InvoiceService
{
    /** Statuses of an order that was paid at some point (a refund does not make the invoice disappear). */
    private const INVOICEABLE = ['paid', 'refunded', 'partially_refunded'];

    public function isInvoiceable(Order $order): bool
    {
        return in_array($order->status, self::INVOICEABLE, true) && $order->paid_at !== null;
    }

    /** Issues the invoice if it does not exist yet (idempotent) and returns the order with the snapshot loaded. */
    public function ensure(Order $order): Order
    {
        if ($order->invoice_number && $order->invoice_snapshot) {
            return $order;
        }

        DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked->invoice_number || ! $locked->invoice_snapshot) {
                $locked->forceFill([
                    'invoice_number' => $locked->invoice_number ?: 'INV-'.$locked->order_number,
                    'invoice_issued_at' => $locked->invoice_issued_at ?: ($locked->paid_at ?? now()),
                    'invoice_snapshot' => $this->snapshot($locked),
                ])->saveQuietly();
            }

            $order->setRawAttributes($locked->getAttributes(), true);
        });

        return $order->refresh();
    }

    public function pdf(Order $order): string
    {
        $order = $this->ensure($order);
        $order->loadMissing(['customer', 'items']);

        return Pdf::loadView('emails.tickets.invoice-pdf', [
            'order' => $order,
            'invoice' => $order->invoice_snapshot,
        ])->output();
    }

    public function filename(Order $order): string
    {
        return 'invoice-'.$order->order_number.'.pdf';
    }

    /** @return array<string, mixed> */
    private function snapshot(Order $order): array
    {
        $total = round((float) $order->total_amount, 2);
        $subtotal = round((float) $order->subtotal_amount, 2);
        $discount = round((float) $order->discount_amount, 2);
        $feesTotal = max(0.0, round($total - ($subtotal - $discount), 2));

        $method = PaymentMethod::query()->where('code', (string) $order->payment_method)->first();

        return [
            'currency' => 'EGP',
            'seller' => [
                'name' => (string) SystemSettings::get('site_name', 'TKT House'),
                'address' => SystemSettings::get('contact_address'),
            ],
            'subtotal' => $subtotal,
            'discount' => $discount,
            'promo_code' => $order->promo_code,
            // The event's own fees / taxes, itemised by the names the organiser gave them.
            'fees' => $this->feeLines($order, $subtotal, $feesTotal),
            'fees_total' => $feesTotal,
            'total' => $total,
            'payment_method' => $method ? trim((string) ($method->checkout_label ?: $method->name)) : (string) $order->payment_method,
        ];
    }

    /**
     * Fee lines as charged: the breakdown stored at checkout when there is one. Older orders only stored the total,
     * so their lines are rebuilt from the event's fees, but only if that reproduces exactly what was charged;
     * otherwise a single "Fees & taxes" line keeps the invoice truthful.
     *
     * @return list<array{name: string, fee_type: string, value: float, amount: float}>
     */
    private function feeLines(Order $order, float $subtotal, float $feesTotal): array
    {
        if ($feesTotal <= 0) {
            return [];
        }

        $stored = collect($order->fees_breakdown ?? []);
        if ($stored->isNotEmpty() && abs($stored->sum('amount') - $feesTotal) < 0.01) {
            return $stored->map(fn (array $f) => $this->line($f['name'], $f['fee_type'], $f['value'], $f['amount']))->all();
        }

        $event = $order->items()->with('event.fees')->get()->pluck('event')->filter()->first();
        if ($event) {
            $rebuilt = app(CheckoutPricing::class)->fees($event, $subtotal);
            if ($rebuilt && abs(array_sum(array_column($rebuilt, 'amount')) - $feesTotal) < 0.01) {
                return array_map(fn (array $f) => $this->line($f['name'], $f['fee_type'], $f['value'], $f['amount']), $rebuilt);
            }
        }

        return [$this->line('Fees & taxes', 'fixed', $feesTotal, $feesTotal)];
    }

    /** @return array{name: string, fee_type: string, value: float, amount: float} */
    private function line(string $name, string $type, mixed $value, mixed $amount): array
    {
        return ['name' => $name, 'fee_type' => $type, 'value' => (float) $value, 'amount' => round((float) $amount, 2)];
    }
}
