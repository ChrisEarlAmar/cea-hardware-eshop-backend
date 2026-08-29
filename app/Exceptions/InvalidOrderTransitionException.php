<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly Order $order,
        public readonly string $attemptedStatus
    ) {
        parent::__construct(
            "Cannot transition order [{$order->id}] from '{$order->status}' to '{$attemptedStatus}'."
        );
    }
}
