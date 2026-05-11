<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Shared\Domain\Event\EventInterface;

final readonly class OrderCreatedEvent implements EventInterface
{
    private \DateTimeImmutable $occurredOn;

    public function __construct(
        public int $orderId,
        public int $userId,
        public int $regionCode,
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
