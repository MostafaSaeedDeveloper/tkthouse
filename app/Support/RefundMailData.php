<?php

namespace App\Support;

use App\Models\PaymentRefund;

/**
 * The ONLY facts a customer refund e-mail may show. Everything is derived from our own records and made customer-safe:
 * no provider / gateway name, no provider reference or id, no dashboard or API wording, no internal note, no staff names.
 */
final class RefundMailData
{
    /** Words that must never reach a customer, even inside a free-text reason typed by staff. */
    private const BLOCKED = '/fawater[a-z]*|paymob|accept\.|gateway|merchant|dashboard|webhook|\bapi\b|credential|secret|internal|https?:|www\.|@|\d{6,}/iu';

    public static function for(PaymentRefund $r): object
    {
        $r->loadMissing(['order.customer', 'order.items.event', 'ticketLinks']);
        $order = $r->order;
        $name = trim((string) $order->customer?->full_name);
        $events = OrderPresenter::eventNames($order)->filter()->unique()->values();
        $canceled = $r->ticketLinks->whereNotNull('canceled_at')->count();
        $selected = $r->ticketLinks->count();

        return (object) [
            'name' => $name !== '' ? $name : null,
            'order_number' => OrderPresenter::number($order),
            'events' => $events->implode(' · '),
            'amount' => number_format($r->amount_minor / 100, 2).' '.($r->currency ?: 'EGP'),
            'is_full' => $r->isFull(),
            'tickets_included' => $selected > 0 ? $selected : null,       // known only when tickets are attached to the refund
            'tickets_canceled' => $canceled,
            'tickets_outcome' => match (true) {                             // what we may say about the tickets after completion
                $r->status !== PaymentRefund::SUCCEEDED => 'unknown',
                $canceled > 0 && $r->isFull() => 'full',
                $canceled > 0 => 'partial',
                $r->effects_status === PaymentRefund::EFFECTS_NONE => 'none',
                default => 'unknown',                                       // needs review: say nothing about tickets
            },
            'reason' => self::safeReason((string) $r->reason),
            'reference' => $r->internal_reference,                          // our own REF-… number, not the provider's
            'confirmed_at' => ($r->completed_at ?? $r->confirmed_at)?->format('d M Y'),
            'timing_note' => self::timingNote(),
        ];
    }

    /** Staff-typed reason made safe for customers: tags/links/ids/provider words → generic fallback; capped length. */
    public static function safeReason(string $reason): string
    {
        $reason = trim(strip_tags($reason));
        if ($reason === '' || preg_match(self::BLOCKED, $reason)) {
            return (string) __('refund_mail.reason_fallback');
        }

        return mb_strimwidth($reason, 0, 140, '…');
    }

    /** Editable in Settings → Notifications; otherwise the translatable default. No official provider timeline exists, so none is promised. */
    public static function timingNote(): string
    {
        $custom = trim((string) SystemSettings::get('refund_email_timing_note', ''));
        if ($custom !== '' && ! preg_match(self::BLOCKED, $custom)) {
            return $custom;
        }

        return (string) __('refund_mail.completed.timing');
    }
}
