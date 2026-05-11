<?php

declare(strict_types=1);

namespace App\Order\Application\Response;

class OrderStatusResponse
{
    public function __construct(
        public string $title,
        public string $code,
    ) {
    }
}
