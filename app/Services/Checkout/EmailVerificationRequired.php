<?php

namespace App\Services\Checkout;

/** The buyer's e-mail belongs to an existing account that this browser has not proven it controls. */
final class EmailVerificationRequired extends \RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct('Please verify your email to continue.');
    }
}
