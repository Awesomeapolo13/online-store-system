<?php

declare(strict_types=1);

namespace App\Order\Application\Response;

final readonly class OrderResponse
{
    public function __construct(
        public ?int $id,
        public int $userId,
        public int $region,
        public bool $isDelivery,
        public OrderStatusResponse $status,
        public string $actualTotalCost,
        /** @var OrderItemResponse[] */
        public array $orderItems,
    ) {
    }
}
