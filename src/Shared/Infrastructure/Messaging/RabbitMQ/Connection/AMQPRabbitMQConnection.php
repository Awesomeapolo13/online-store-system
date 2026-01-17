<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Connection;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

final readonly class AMQPRabbitMQConnection
{
    private AMQPStreamConnection $connection;

    /**
     * @throws \Exception
     */
    public function __construct(
        string $rabbitHost,
        int $rabbitPort,
        string $rabbitUser,
        string $rabbitPassword,
        string $rabbitVhost = '/',
    ) {
        $this->connection = new AMQPStreamConnection(
            $rabbitHost,
            $rabbitPort,
            $rabbitUser,
            $rabbitPassword,
            $rabbitVhost,
        );
    }

    public function getConnection(): AMQPStreamConnection
    {
        return $this->connection;
    }

    public function getChannel(): AMQPChannel
    {
        return $this->connection->channel();
    }

    /**
     * @throws \Exception
     */
    public function close(): void
    {
        $this->connection->close();
    }
}
