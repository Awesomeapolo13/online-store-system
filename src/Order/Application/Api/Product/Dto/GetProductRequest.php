<?php

declare(strict_types=1);

namespace App\Order\Application\Api\Product\Dto;

final readonly class GetProductRequest
{
    public function __construct(
        public string $supCode,
        public int $shopNumber,
        public int $region,
    ) {
    }
}
