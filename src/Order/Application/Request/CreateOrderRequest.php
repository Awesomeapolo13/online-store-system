<?php

declare(strict_types=1);

namespace App\Order\Application\Request;

final readonly class CreateOrderRequest
{
    public function __construct(
        public int $userId,
        public int $region,
    ) {
    }
}
