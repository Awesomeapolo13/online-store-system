<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Config;

final readonly class QueueConfigElement
{
    public function __construct(
        public string $name,
        public bool $durable,
        public bool $autoDelete,
        public bool $exclusive,
        public array $arguments,
        public array $bindings,
    ) {
    }
}
