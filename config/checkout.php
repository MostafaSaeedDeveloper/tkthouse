<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Checkout experience
    |--------------------------------------------------------------------------
    | wizard   → one step at a time on /checkout: Your details → each ticket holder → Review & payment.
    | one-page → the original single page with every section stacked.
    | Both talk to the same endpoints and the same server-side rules; the choice only changes presentation.
    | Outside production /checkout?experience=wizard|one-page switches (remembered in the session) so the two can be
    | compared on staging. Production always follows this setting.
    */
    'experience' => in_array(env('CHECKOUT_EXPERIENCE', 'wizard'), ['wizard', 'one-page'], true) ? env('CHECKOUT_EXPERIENCE', 'wizard') : 'wizard',
];
