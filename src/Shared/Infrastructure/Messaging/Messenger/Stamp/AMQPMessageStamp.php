<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class AMQPMessageStamp implements StampInterface
{
    public function __construct(
        public int $deliveryTag,
        public string $queueName,
    ) {}
}
