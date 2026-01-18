<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Config;

class RabbitMQInfrastructureConfigFactory
{
    public function createFromArray(array $config): RabbitMQInfrastructureConfig
    {
        $exchanges = [];
        foreach ($config['exchanges'] ?? [] as $name => $exchangeData) {
            $exchanges[] = new ExchangeConfigElement(
                name: $name,
                type: $exchangeData['type'] ?? 'direct',
                durable: $exchangeData['durable'] ?? true,
                autoDelete: $exchangeData['auto_delete'] ?? false,
                internal: $exchangeData['internal'] ?? false,
            );
        }

        $queues = [];
        foreach ($config['queues'] ?? [] as $name => $queueData) {
            $bindings = [];
            foreach ($queueData['bindings'] ?? [] as $bindingData) {
                $bindings[] = new QueueBindingConfigElement(
                    exchange: $bindingData['exchange'],
                    routingKey: $bindingData['routing_key'],
                );
            }

            $queues[] = new QueueConfigElement(
                name: $name,
                durable: $queueData['durable'] ?? true,
                autoDelete: $queueData['auto_delete'] ?? false,
                exclusive: $queueData['exclusive'] ?? false,
                arguments: $queueData['arguments'] ?? [],
                bindings: $bindings,
            );
        }

        return new RabbitMQInfrastructureConfig(
            exchanges: $exchanges,
            queues: $queues,
        );
    }
}
