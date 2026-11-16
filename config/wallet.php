<?php

/**
 * "Add to Apple Wallet". The button only appears once every value below is set (see ApplePassBuilder::configured()).
 * Needs an Apple Developer "Pass Type ID" certificate exported as .p12, plus Apple's WWDR intermediate certificate (.pem).
 */
return [
    'apple' => [
        'pass_type_id' => env('WALLET_APPLE_PASS_TYPE_ID'),          // e.g. pass.com.yourcompany.tickets
        'team_id' => env('WALLET_APPLE_TEAM_ID'),                    // 10-character Apple Developer Team ID
        'certificate_path' => env('WALLET_APPLE_CERT_PATH'),         // absolute path to the Pass Type ID certificate (.p12)
        'certificate_password' => env('WALLET_APPLE_CERT_PASSWORD'),
        'wwdr_path' => env('WALLET_APPLE_WWDR_PATH'),                // absolute path to Apple WWDR certificate (.pem)
        'organization_name' => env('WALLET_APPLE_ORG_NAME', env('APP_NAME', 'Tickets')),
        'background_color' => env('WALLET_APPLE_BG', 'rgb(17, 17, 17)'),
        'foreground_color' => env('WALLET_APPLE_FG', 'rgb(255, 255, 255)'),
        'label_color' => env('WALLET_APPLE_LABEL', 'rgb(255, 204, 0)'),
    ],
];
