<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ;

use App\Shared\Infrastructure\Messaging\RabbitMQ\Config\RabbitMQInfrastructureConfig;
use App\Shared\Infrastructure\Messaging\RabbitMQ\Connection\AMQPRabbitMQConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;

final readonly class RabbitMQInfrastructureInitializingService
{
    public function __construct(
        private AMQPRabbitMQConnection $connection,
        private RabbitMQInfrastructureConfig $config,
        private LoggerInterface $logger,
    ) {
    }

    public function initialize(): void
    {
        try {
            $channel = $this->connection->getChannel();

            $this->declareExchanges($channel, $this->config);
            $this->declareQueuesAndBindings($channel, $this->config);

            $channel->close();
        } finally {
            $this->connection->close();
        }
    }

    private function declareExchanges(AMQPChannel $channel, RabbitMQInfrastructureConfig $config): void
    {
        /** @var  $exchange */
        foreach ($config->exchanges as $exchange) {
            $this->logger->info(
                "Declared exchange {$exchange->name}",
                [
                    'name' => $exchange->name,
                    'type' => $exchange->type,
                ]
            );

            $channel->exchange_declare(
                exchange: $exchange->name,
                type: $exchange->type,
                durable: $exchange->durable,
                auto_delete: $exchange->autoDelete,
                internal: $exchange->internal,
            );
        }
    }

    private function declareQueuesAndBindings(AMQPChannel $channel, RabbitMQInfrastructureConfig $config): void
    {
        foreach ($config->queues as $queue) {
            $this->logger->info(
                "Declared queue {$queue->name}",
                [
                    'name' => $queue->name,
                    'binding_count' => count($queue->bindings),
                ]
            );

            $arguments = new AMQPTable($queue->arguments);
            $channel->queue_declare(
                queue: $queue->name,
                durable: $queue->durable,
                exclusive: $queue->exclusive,
                auto_delete: $queue->autoDelete,
                arguments: $arguments,
            );

            foreach ($queue->bindings as $binding) {
                $this->logger->info(
                    "Binding queue {$queue->name} to exchange {$binding->exchange}",
                    [
                        'queue' => $queue->name,
                        'exchange' => $binding->exchange,
                        'routing_key' => $binding->routingKey,
                    ]
                );

                $channel->queue_bind(
                    queue: $queue->name,
                    exchange: $binding->exchange,
                    routing_key: $binding->routingKey,
                );
            }
        }
    }
}
