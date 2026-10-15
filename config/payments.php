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
    | Where payment status is decided: always by asking the gateway server-to-server. Browser
    | redirects and callback bodies only tell us *which* transaction to look up.
    */
    'reconcile' => [
        'older_than_minutes' => 2,   // give the normal callback a moment first
        'max_age_hours' => 48,       // stop polling attempts older than this
    ],

    'fawaterak' => [
        // Server-side invoice lookup used to confirm a payment. Paths/fields are configurable so they can be
        // matched to the merchant's Fawaterak API version without a code change.
        'invoice_status_path' => env('FAWATERAK_INVOICE_STATUS_PATH', 'getInvoiceData/{id}'),
        'paid_field' => env('FAWATERAK_PAID_FIELD', 'data.paid'),
        'amount_field' => env('FAWATERAK_AMOUNT_FIELD', 'data.total'),
    ],

    'paymob' => [
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
    ],
];
