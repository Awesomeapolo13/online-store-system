<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Config;

final readonly class QueueBindingConfigElement
{
    public function __construct(
        public string $exchange,
        public string $routingKey,
    ) {
    }
}
