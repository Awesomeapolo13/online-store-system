<?php

declare(strict_types=1);

namespace App\Order\Application\Request;

final readonly class AddProductToCartRequest
{
    public function __construct(
        public int $userId,
        public int $region,
        public string $supCode,
        public int $quantity,
        public string $orderDate,
    ) {
    }
}
