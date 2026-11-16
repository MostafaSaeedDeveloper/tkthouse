<?php

/** "Add to Apple Wallet": credentials live in Settings → Apple Wallet (database, certificate encrypted). Only the pass look is configured here. */
return [
    'apple' => [
        'background_color' => env('WALLET_APPLE_BG', 'rgb(17, 17, 17)'),
        'foreground_color' => env('WALLET_APPLE_FG', 'rgb(255, 255, 255)'),
        'label_color' => env('WALLET_APPLE_LABEL', 'rgb(255, 204, 0)'),
    ],
];
