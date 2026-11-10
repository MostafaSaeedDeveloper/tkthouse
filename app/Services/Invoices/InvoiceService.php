<?php

namespace App\Services\Invoices;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Support\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Paid-order invoices. The invoice is a snapshot written once (number, date, seller, tax figures) the first time it is
 * needed, so changing the tax rate or company details later never rewrites an invoice a customer already received.
 * Prices are tax-inclusive: the tax is broken out of the order total and the amount charged is never touched.
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
        $fees = round($total - ($subtotal - $discount), 2);

        $taxEnabled = (bool) SystemSettings::get('invoice_tax_enabled', true);
        $rate = $taxEnabled ? round(max(0.0, (float) SystemSettings::get('invoice_tax_rate', 14)), 2) : 0.0;
        $taxEnabled = $taxEnabled && $rate > 0;

        $net = $taxEnabled ? round($total / (1 + $rate / 100), 2) : $total;
        $tax = $taxEnabled ? round($total - $net, 2) : 0.0;

        $method = PaymentMethod::query()->where('code', (string) $order->payment_method)->first();

        return [
            'currency' => 'EGP',
            'seller' => [
                'name' => (string) (SystemSettings::get('invoice_company_name') ?: SystemSettings::get('site_name', 'TKT House')),
                'tax_number' => SystemSettings::get('invoice_tax_number'),
                'address' => SystemSettings::get('invoice_company_address') ?: SystemSettings::get('contact_address'),
            ],
            'tax' => [
                'enabled' => $taxEnabled,
                'label' => (string) SystemSettings::get('invoice_tax_label', 'VAT'),
                'rate' => $rate,
            ],
            'subtotal' => $subtotal,
            'discount' => $discount,
            'promo_code' => $order->promo_code,
            'fees' => $fees > 0 ? $fees : 0.0,
            'net' => $net,
            'tax_amount' => $tax,
            'total' => $total,
            'payment_method' => $method ? trim((string) ($method->checkout_label ?: $method->name)) : (string) $order->payment_method,
        ];
    }
}
