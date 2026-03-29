<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CartSetUp;

use App\Shared\Application\Command\CommandInterface;

final readonly class CartSetUpCommand implements CommandInterface
{
    public function __construct(
        public int $userId,
        public int $regionCode,
        public bool $isDelivery,
        public \DateTimeImmutable $orderDate,
        public ?int $shopId,
    ) {
    }
}
