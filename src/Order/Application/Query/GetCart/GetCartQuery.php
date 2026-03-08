<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetCart;

use App\Shared\Application\Query\QueryInterface;

final readonly class GetCartQuery implements QueryInterface
{
    public function __construct(
        public int $userId,
        public int $regionCode,
    ) {
    }
}
