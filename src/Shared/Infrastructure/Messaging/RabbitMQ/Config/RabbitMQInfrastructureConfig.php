<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Config;

final readonly class RabbitMQInfrastructureConfig
{
    public function __construct(
        public array $exchanges,
        public array $queues,
    ) {
    }
}
