<?php

namespace App\Services\Checkout;

/** The e-mail belongs to a staff (back-office) account, which can never be used through the passwordless customer flow. */
final class StaffEmailNotAllowed extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This email belongs to a staff account. Please use a different email address, or sign in with your password.');
    }
}
