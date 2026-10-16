<?php

namespace App\Services\Payments\Routing;

use Illuminate\Validation\ValidationException;

/** The customer's provisional agent/account/methods are no longer valid: they must choose payment again (data is kept). */
class ReservationInvalid extends ValidationException
{
    public static function because(string $reason): self
    {
        $e = static::withMessages([
            'price_changed' => 'Your payment options changed or expired. Please review the available payment methods and choose again — your details are saved.',
        ]);
        $e->reason = $reason;

        return $e;
    }

    public string $reason = '';
}
