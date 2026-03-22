<?php

declare(strict_types=1);

namespace App\Order\Application\Command\AddProductToCart;

use App\Shared\Application\Command\CommandInterface;

final readonly class AddProductToCartCommand implements CommandInterface
{
    public function __construct(
        public int $userId,
        public int $regionCode,
        public string $supCode,
        public int $quantity,
    ) {
    }
}
