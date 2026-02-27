<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Messenger\Transport;

use App\Shared\Infrastructure\Messaging\Messenger\Stamp\AMQPRoutingKeyStamp;
use App\Shared\Infrastructure\Messaging\RabbitMQ\Connection\AMQPRabbitMQConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class AMQPSender implements SenderInterface
{
    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly AMQPRabbitMQConnection $connectionManager,
        private readonly SerializerInterface $serializer,
        private readonly string $exchangeName,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws \Exception
     */
    public function send(Envelope $envelope): Envelope
    {
        $this->setupChannel();

        // Получаем routing key из stamp
        $routingKeyStamp = $envelope->last(AMQPRoutingKeyStamp::class);
        $routingKey = $routingKeyStamp?->routingKey ?? '';

        if ($routingKey === '') {
            throw new TransportException('Message is missing routing key');
        }

        // Сериализуем envelope
        $encodedMessage = $this->serializer->encode($envelope);

        $body = $encodedMessage['body'] ?? '';
        $headers = $encodedMessage['headers'] ?? [];

        // Создаем AMQP сообщение
        $amqpMessage = new AMQPMessage($body, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'content_type' => 'application/json',
            'application_headers' => $headers,
        ]);

        try {
            // Публикуем сообщение
            $this->channel->basic_publish(
                msg: $amqpMessage,
                exchange: $this->exchangeName,
                routing_key: $routingKey,
            );

            $this->logger->debug('Message published', [
                'exchange' => $this->exchangeName,
                'routing_key' => $routingKey,
                'message_type' => $headers['type'] ?? 'unknown',
            ]);

        } catch (\Throwable $exception) {
            throw new TransportException(
                'Failed to publish message: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        return $envelope;
    }

    /**
     * @throws \Exception
     */
    private function setupChannel(): void
    {
        if ($this->channel !== null) {
            return;
        }

        $this->channel = $this->connectionManager->getChannel();

        $this->logger->info('AMQP sender channel created', [
            'exchange' => $this->exchangeName,
        ]);
    }

    public function __destruct()
    {
        if ($this->channel !== null) {
            try {
                $this->channel->close();
            } catch (\Throwable $e) {
                // Игнорируем ошибки при закрытии
            }
        }
    }
}
