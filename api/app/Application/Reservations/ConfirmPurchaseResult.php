<?php

namespace App\Application\Reservations;

use App\Models\Order;

final readonly class ConfirmPurchaseResult
{
    public function __construct(
        public Order $order,
        public bool $created,
    ) {
    }
}
