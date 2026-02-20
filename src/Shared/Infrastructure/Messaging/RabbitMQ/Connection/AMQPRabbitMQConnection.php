<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Connection;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

final class AMQPRabbitMQConnection
{
    private ?AMQPStreamConnection $connection = null;

    public function __construct(
        private readonly string $rabbitHost,
        private readonly int $rabbitPort,
        private readonly string $rabbitUser,
        private readonly string $rabbitPassword,
        private readonly string $rabbitVhost = '/',
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getConnection(): AMQPStreamConnection
    {
        if (!$this->connection?->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                $this->rabbitHost,
                $this->rabbitPort,
                $this->rabbitUser,
                $this->rabbitPassword,
                $this->rabbitVhost,
            );
        }

        return $this->connection;
    }

    /**
     * @throws \Exception
     */
    public function getChannel(): AMQPChannel
    {
        return $this->getConnection()->channel();
    }

    /**
     * @throws \Exception
     */
    public function close(): void
    {
        if ($this->connection?->isConnected()) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
