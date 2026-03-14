<?php

declare(strict_types=1);

namespace App\Order\Application\Response;

final readonly class CartResponse
{
    /** @param CartItemResponse[] $cartItems */
    public function __construct(
        public int $id,
        public ?int $userId,
        public ?int $shopNum,
        public int $region,
        public bool $isDelivery,
        public bool $isExpress,
        public string $orderDate,
        public string $totalCost,
        public array $cartItems,
    ) {
    }
}
