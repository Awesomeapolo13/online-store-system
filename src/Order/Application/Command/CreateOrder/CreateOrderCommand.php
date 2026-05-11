<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CreateOrder;

use App\Shared\Application\Command\CommandInterface;

final readonly class CreateOrderCommand implements CommandInterface
{
    public function __construct(
        public int $userId,
        public int $regionCode,
    ) {
    }
}
