<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Config;

final readonly class ExchangeConfigElement
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $durable,
        public bool $autoDelete,
        public bool $internal,
    ) {
    }
}
