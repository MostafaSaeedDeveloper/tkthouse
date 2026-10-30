<?php

return [
    /*
    | Fake gateway: a local simulator used for automated tests and for dev/staging walkthroughs.
    | It can never be enabled in production, whatever the environment variable says.
    */
    'fake' => [
        'enabled' => env('APP_ENV') !== 'production' && (bool) env('PAYMENTS_FAKE_ENABLED', false),
    ],

    /*
    | Real gateways (Paymob, Fawaterak). Production: always on. Anywhere else (staging with a copy of production
    | data, whose payment methods still hold production credentials): OFF unless PAYMENTS_LIVE_GATEWAYS=true,
    | which must only be set together with sandbox credentials.
    */
    'live_gateways' => [
        'enabled' => filter_var(env('PAYMENTS_LIVE_GATEWAYS', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | Where payment status is decided: always by asking the gateway server-to-server. Browser
    | redirects and callback bodies only tell us *which* transaction to look up.
    */
    'reconcile' => [
        'older_than_minutes' => 2,   // give the normal callback a moment first
        'max_age_hours' => 48,       // stop polling attempts older than this
    ],

    'fawaterak' => [
        // Official reference (fawaterak-api.readme.io, "Get Transaction Data"): GET {base}/getInvoiceData/{invoice_id}
        // with a Bearer token; the example response has data.invoice_id, data.total, data.currency, data.paid and
        // data.paid_at. The docs do NOT explain the meaning of `paid`, so production use is gated behind a real
        // sandbox run: set PAYMENTS_FAWATERAK_VERIFIED=true only after that.
        'invoice_status_path' => env('FAWATERAK_INVOICE_STATUS_PATH', 'getInvoiceData/{id}'),
        'verified' => filter_var(env('PAYMENTS_FAWATERAK_VERIFIED', false), FILTER_VALIDATE_BOOLEAN),
        // Fawaterak publishes NO documented merchant-dashboard deep link to an invoice/transaction, so none is invented. Optionally set the
        // address of YOUR authenticated Fawaterak merchant dashboard (https only, no credentials or query) so staff can open it in one click
        // and paste the copied invoice id. Defaults to the merchant dashboard address given by the operator (https://app.fawaterk.com/); no .env change needed.
        'dashboard_url' => env('FAWATERAK_DASHBOARD_URL', 'https://app.fawaterk.com/'),
        // Optional: the address of ONE transaction page in your Fawaterak dashboard, copied from your browser while viewing an invoice, with
        // the invoice id replaced by {id} (e.g. https://<your-dashboard>/invoices/{id}). Used only when set; never guessed.
        'transaction_url' => env('FAWATERAK_TRANSACTION_URL'),
    ],

    /*
    | Provider refund APIs. OFF by default everywhere: real refund calls need this flag AND an account that an operator marked
    | "refunds verified" after the sandbox runbook. Non-production environments may use sandbox accounts without the flag only
    | for the fake provider.
    */
    'refunds' => [
        'live_api_enabled' => filter_var(env('PAYMENTS_REFUNDS_LIVE', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'paymob' => [
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        // Always enforced in production; can be forced on elsewhere.
        'require_hmac' => filter_var(env('PAYMOB_REQUIRE_HMAC', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
