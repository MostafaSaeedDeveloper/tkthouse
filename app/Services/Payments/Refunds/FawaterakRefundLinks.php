<?php

namespace App\Services\Payments\Refunds;

use App\Models\PaymentRefund;

/**
 * What staff need to find the ORIGINAL Fawaterak payment in the merchant dashboard. Nothing here is guessed:
 *  - ids come only from what TKT House stored (the attempt's invoice id / reference number, or an id a person recorded on a legacy order);
 *  - the dashboard address comes only from configuration (Fawaterak documents no deep link), is https-only and never carries credentials;
 *  - the customer payment URL is never used.
 */
final class FawaterakRefundLinks
{
    /** @return array{invoice_id: ?string, reference_number: ?string, source: string} source: attempt | recorded | none */
    public static function references(PaymentRefund $refund): array
    {
        $attempt = $refund->attempt;
        $recorded = trim((string) data_get($refund->meta, 'original_transaction_ref'));
        $invoice = trim((string) ($attempt?->transaction_id ?? ''));
        $ref = trim((string) ($attempt?->provider_reference ?? ''));

        if ($invoice === '' && $ref === '' && $recorded !== '') {
            return ['invoice_id' => $recorded, 'reference_number' => null, 'source' => 'recorded'];
        }

        return ['invoice_id' => $invoice !== '' ? $invoice : null, 'reference_number' => $ref !== '' ? $ref : null, 'source' => ($invoice !== '' || $ref !== '') ? 'attempt' : 'none'];
    }

    /** The configured authenticated dashboard address, or null when not configured / unsafe. */
    public static function dashboardUrl(): ?string
    {
        $url = trim((string) config('payments.fawaterak.dashboard_url'));
        $p = $url === '' ? false : parse_url($url);
        if (! $p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
            return null;
        }

        return $url;
    }
}
