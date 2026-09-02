<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;
use Throwable;

class PaymentStartFailedException extends RuntimeException
{
    public function __construct(
        public readonly Order $order,
        Throwable $previous,
    ) {
        parent::__construct(
            'Could not start payment for this order. No charge was made; please try again.',
            previous: $previous,
        );
    }
}
