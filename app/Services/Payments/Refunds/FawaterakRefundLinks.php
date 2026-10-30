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

    /**
     * Optional operator-supplied pattern for the link to ONE transaction in the merchant dashboard, e.g. https://host/path/{id}.
     * Fawaterak documents no such link, so none is built into the code: it is only used when the operator copied the pattern
     * from their own dashboard. https only, no credentials, and it must contain {id}.
     */
    public static function transactionUrl(?string $id): ?string
    {
        $tpl = trim((string) config('payments.fawaterak.transaction_url'));
        $id = trim((string) $id);
        if ($tpl === '' || $id === '' || substr_count($tpl, '{id}') !== 1) {
            return null;
        }
        $p = parse_url(str_replace('{id}', 'x', $tpl));
        if (! $p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            return null;
        }

        return str_replace('{id}', rawurlencode($id), $tpl);
    }

    /** The page to open for this payment: the exact transaction when a pattern is configured, else the dashboard. */
    public static function openUrl(?string $id): ?string
    {
        return self::transactionUrl($id) ?? self::dashboardUrl();
    }

    public static function anyConfigured(): bool
    {
        return self::transactionUrl('x') !== null || self::dashboardUrl() !== null;
    }
}
