<?php

namespace App\Services\Payments;

/** Thrown inside the settlement transaction when a gateway transaction id is already bound to another attempt. */
final class TransactionReused extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Gateway transaction is already bound to another payment attempt.');
    }
}
