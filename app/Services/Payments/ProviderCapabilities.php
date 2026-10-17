<?php

namespace App\Services\Payments;

/**
 * What each provider's OFFICIAL API supports (docs/v2-provider-capabilities-audit.md). The UI asks this class before it shows a
 * button, so an unsupported function can never appear as a working one. "Supported" means API only; everything else is labelled.
 */
final class ProviderCapabilities
{
    public const API = 'api';        // supported by an official API
    public const ACT = 'activation'; // needs provider activation / enablement
    public const MAN = 'manual';     // manual / external only (provider dashboard)
    public const NO = 'no';          // not supported
    public const ND = 'nd';          // not documented in any official source (treated as unavailable)

    public const LABELS = [
        self::API => 'Supported (official API)', self::ACT => 'Needs provider activation', self::MAN => 'Manual / provider dashboard only',
        self::NO => 'Not supported', self::ND => 'Not documented by the provider',
    ];

    public const CAPABILITIES = [
        'list_transactions' => 'List all transactions', 'search_transactions' => 'Search transactions', 'transaction_details' => 'Transaction details',
        'status_inquiry' => 'Payment status inquiry', 'refund' => 'Refund', 'partial_refund' => 'Partial refund', 'void' => 'Void', 'cancel' => 'Cancel',
        'payment_link' => 'Payment link / invoice details', 'references' => 'Transaction references', 'fees' => 'Fees', 'webhooks' => 'Webhooks',
        'history' => 'Transaction history', 'export' => 'Export / reports',
    ];

    /** @return array<string, array<string, array{0:string,1:bool,2:string}>> capability => provider => [status, needs sandbox verification, note] */
    public static function matrix(): array
    {
        return [
            'list_transactions' => [
                'paymob' => [self::ND, false, 'No official listing API (single lookups only). Use CSV import of the dashboard export, or import by id.'],
                'fawaterak' => [self::ND, false, 'No official listing API. Use CSV import of the dashboard export, or import by invoice id.'],
            ],
            'search_transactions' => [
                'paymob' => [self::API, true, 'By transaction id, Paymob order id or merchant order id only (no date/status search).'],
                'fawaterak' => [self::API, true, 'By invoice id only (getInvoiceData).'],
            ],
            'transaction_details' => [
                'paymob' => [self::API, true, 'GET /api/acceptance/transactions/{id}.'],
                'fawaterak' => [self::API, true, 'GET /api/v2/getInvoiceData/{id}.'],
            ],
            'status_inquiry' => [
                'paymob' => [self::API, true, 'Server-to-server inquiry with the original merchant account.'],
                'fawaterak' => [self::API, true, 'Server-to-server inquiry with the original merchant account.'],
            ],
            'refund' => [
                'paymob' => [self::API, true, 'POST /api/acceptance/void_refund/refund (needs the account to be marked refund-verified and PAYMENTS_REFUNDS_LIVE).'],
                'fawaterak' => [self::MAN, false, 'No refund-creation API: refund in the Fawaterak dashboard, then record it here with evidence.'],
            ],
            'partial_refund' => [
                'paymob' => [self::API, true, 'amount_cents up to the original amount.'],
                'fawaterak' => [self::MAN, false, 'Dashboard only.'],
            ],
            'void' => [
                'paymob' => [self::API, true, 'Card payments only, same business day, no fee.'],
                'fawaterak' => [self::NO, false, 'No void API.'],
            ],
            'cancel' => [
                'paymob' => [self::NO, false, 'Same as void (no separate cancel).'],
                'fawaterak' => [self::NO, false, 'No cancel API; an expiry webhook is sent for Fawry/Aman/Masary references.'],
            ],
            'payment_link' => [
                'paymob' => [self::API, false, 'Not used by TKT House checkout; details come from the transaction lookup.'],
                'fawaterak' => [self::API, true, 'Create e-invoice link / Send payment; details via getInvoiceData.'],
            ],
            'references' => [
                'paymob' => [self::API, true, 'transaction id, order id, merchant_order_id.'],
                'fawaterak' => [self::API, true, 'invoice_id, invoice_key, referenceNumber.'],
            ],
            'fees' => [
                'paymob' => [self::ND, false, 'No per-transaction fee field is documented.'],
                'fawaterak' => [self::API, true, '"commission" field of getInvoiceData (meaning to be confirmed).'],
            ],
            'webhooks' => [
                'paymob' => [self::API, true, 'Processed callback with per-account HMAC.'],
                'fawaterak' => [self::API, true, 'Paid / failed / cancel / refund (refund and failed are unsigned).'],
            ],
            'history' => [
                'paymob' => [self::MAN, false, 'No history API: built from our attempts, webhook log and the provider flags.'],
                'fawaterak' => [self::API, true, 'invoice_transactions[] lists the attempts on an invoice.'],
            ],
            'export' => [
                'paymob' => [self::MAN, false, 'Dashboard export; import it here as CSV.'],
                'fawaterak' => [self::MAN, false, 'Dashboard export; import it here as CSV.'],
            ],
        ];
    }

    public static function status(string $provider, string $capability): string
    {
        return self::matrix()[$capability][$provider][0] ?? self::ND;
    }

    public static function supports(string $provider, string $capability): bool
    {
        return self::status($provider, $capability) === self::API;
    }

    public static function note(string $provider, string $capability): string
    {
        return self::matrix()[$capability][$provider][2] ?? '';
    }

    /** Providers for which a real, official "look up one transaction" call exists (the only thing import-by-id and refresh can use). */
    public static function canLookup(string $provider): bool
    {
        return in_array($provider, ['paymob', 'fawaterak', 'fake'], true) && ($provider === 'fake' || self::supports($provider, 'transaction_details'));
    }

    /** There is deliberately no provider for which "sync everything" is possible. */
    public static function canListAll(string $provider): bool
    {
        return self::supports($provider, 'list_transactions');
    }
}
