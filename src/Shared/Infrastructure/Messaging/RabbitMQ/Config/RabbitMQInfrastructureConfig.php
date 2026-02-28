<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Config;

final readonly class RabbitMQInfrastructureConfig
{
    /**
     * @param ExchangeConfigElement[] $exchanges
     * @param QueueConfigElement[]    $queues
     */
    public function __construct(
        public array $exchanges,
        public array $queues,
    ) {
    }
}
