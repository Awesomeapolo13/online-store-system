<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CreateNewCart;

use App\Order\Domain\ValueObject\Region;
use App\Shared\Application\Command\CommandInterface;

final readonly class CreateNewCartCommand implements CommandInterface
{
    public function __construct(
        public int $userId,
        public Region $region,
    ) {
    }
}
