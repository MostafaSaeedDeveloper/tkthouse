<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],


    'fawaterak' => [
        'api_url' => env('FAWATERAK_API_URL', 'https://app.fawaterk.com/api/v2'),
        'api_key' => env('FAWATERAK_API_KEY'),
        'provider_default' => env('FAWATERAK_PROVIDER_KEY'),
        'provider_card' => env('FAWATERAK_PROVIDER_CARD'),
        'provider_wallet' => env('FAWATERAK_PROVIDER_WALLET'),
        'provider_apple_pay' => env('FAWATERAK_PROVIDER_APPLE_PAY'),
    ],

    'paymob' => [
        'api_key' => env('PAYMOB_API_KEY'),
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'iframe_id' => env('PAYMOB_IFRAME_ID'),
        'integration_card' => env('PAYMOB_INTEGRATION_CARD'),
        'integration_wallet' => env('PAYMOB_INTEGRATION_WALLET'),
        'integration_apple_pay' => env('PAYMOB_INTEGRATION_APPLE_PAY'),
        'currency' => env('PAYMOB_CURRENCY', 'EGP'),
    ],

];
