<?php

declare(strict_types=1);

namespace App\Order\Application\Request;

final readonly class CartSetUpRequest
{
    public function __construct(
        public int $userId,
        public int $region,
        public string $orderDate,
        public bool $isDelivery,
        public ?int $shopId = null,
    ) {
    }
}
