<?php

declare(strict_types=1);

namespace App\Order\Application\Command\RemoveProductFromCart;

use App\Shared\Application\Command\CommandInterface;

final readonly class RemoveProductFromCartCommand implements CommandInterface
{
    public function __construct(
        public int $userId,
        public int $regionCode,
        public string $supCode,
    ) {
    }
}
