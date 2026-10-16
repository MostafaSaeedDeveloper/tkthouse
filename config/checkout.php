<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Attendee wizard (one ticket at a time)
    |--------------------------------------------------------------------------
    | true  → the attendee part of the express checkout shows one ticket at a time with Back / Next.
    | false → the original stacked list of every attendee form.
    | Outside production the page also accepts ?wizard=1 / ?wizard=0 (remembered in the session) so the two
    | designs can be compared side by side. Production always follows this flag.
    */
    'attendee_wizard' => filter_var(env('CHECKOUT_ATTENDEE_WIZARD', true), FILTER_VALIDATE_BOOLEAN),
];
