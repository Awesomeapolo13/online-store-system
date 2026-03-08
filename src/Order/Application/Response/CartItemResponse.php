<?php

declare(strict_types=1);

namespace App\Order\Application\Response;

final readonly class CartItemResponse
{
    public function __construct(
        public int $id,
        public string $supCode,
        public string $perItemPrice,
        public string $totalCost,
        public int $quantity,
    ) {
    }
}
