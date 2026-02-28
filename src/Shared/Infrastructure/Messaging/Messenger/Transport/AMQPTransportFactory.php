<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Messenger\Transport;

use App\Shared\Infrastructure\Messaging\RabbitMQ\Connection\AMQPRabbitMQConnection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class AMQPTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        // Парсим DSN: amqp-consume://user:password@host:port/vhost
        $parsedDsn = parse_url($dsn);

        if ($parsedDsn === false) {
            throw new \InvalidArgumentException("Invalid DSN: {$dsn}");
        }

        $host = $parsedDsn['host'] ?? 'localhost';
        $port = $parsedDsn['port'] ?? 5672;
        $user = $parsedDsn['user'] ?? 'guest';
        $password = $parsedDsn['pass'] ?? 'guest';

        $vhost = '/'; // значение по умолчанию
        if (isset($parsedDsn['path']) && $parsedDsn['path'] !== '/') {
            $vhost = ltrim($parsedDsn['path'], '/');
        }

        // Получаем параметры из options
        $exchangeName = $options['exchange']['name'] ?? 'messages';
        $queueNames = array_keys($options['queues'] ?? []);
        $prefetchCount = $options['prefetch_count'] ?? 10;

        if (empty($queueNames)) {
            throw new \InvalidArgumentException('At least one queue must be specified');
        }

        // Создаем connection manager
        $connectionManager = new AMQPRabbitMQConnection(
            rabbitHost: $host,
            rabbitPort: $port,
            rabbitUser: $user,
            rabbitPassword: $password,
            rabbitVhost: $vhost,
        );

        // Создаем receiver и sender
        $receiver = new AMQPReceiver(
            connectionManager: $connectionManager,
            serializer: $serializer,
            queueNames: $queueNames,
            logger: $this->logger,
            prefetchCount: $prefetchCount,
        );

        $sender = new AMQPSender(
            connectionManager: $connectionManager,
            serializer: $serializer,
            exchangeName: $exchangeName,
            logger: $this->logger,
        );

        return new AMQPTransport($receiver, $sender);
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'amqp-consume://');
    }
}
