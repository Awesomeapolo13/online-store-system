<?php

declare(strict_types=1);

namespace App\Order\Application\Api\Product\Dto;

final readonly class GetProductResponse
{
    public function __construct(
        public string $supCode,
        public string $pricePerItem,
        public bool $isAvailableForOrder,
    ) {
    }
}
