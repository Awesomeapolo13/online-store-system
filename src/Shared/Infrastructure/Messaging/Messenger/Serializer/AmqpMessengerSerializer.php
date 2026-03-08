<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Messenger\Serializer;

use App\Shared\Infrastructure\Messaging\Messenger\Stamp\AMQPRoutingKeyStamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

final readonly class AmqpMessengerSerializer implements SerializerInterface
{
    public function __construct(
        private SymfonySerializerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $body = $encodedEnvelope['body'];
        $headers = $encodedEnvelope['headers'] ?? [];

        $this->logger->debug('AmqpMessengerSerializer::decode entry', [
            'headers' => $headers,
            'body_preview' => substr((string) $body, 0, 500),
        ]);

        $type = $headers['type'] ?? null;

        if ($type === null) {
            $this->logger->error('AmqpMessengerSerializer::decode failed — missing type header', [
                'headers' => $headers,
                'body' => $body,
            ]);

            throw new \InvalidArgumentException('Missing message type header');
        }

        try {
            $message = $this->serializer->deserialize(
                $body,
                $type,
                'json',
            );

            $envelope = new Envelope($message);

            // Восстанавливаем routing key из headers
            if (isset($headers['routing_key'])) {
                $envelope = $envelope->with(
                    new AMQPRoutingKeyStamp($headers['routing_key']),
                );
            }

            return $envelope;
        } catch (\Throwable $exception) {
            $this->logger->error('AmqpMessengerSerializer::decode failed — deserialization error', [
                'headers' => $headers,
                'body' => $body,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        $this->logger->debug('AmqpMessengerSerializer::encode entry', [
            'message_class' => $message::class,
        ]);

        try {
            $body = $this->serializer->serialize($message, 'json');

            $headers = [
                'type' => $message::class,
                'content_type' => 'application/json',
            ];

            // Добавляем routing key в headers
            $routingKeyStamp = $envelope->last(AMQPRoutingKeyStamp::class);
            if ($routingKeyStamp instanceof AMQPRoutingKeyStamp) {
                $headers['routing_key'] = $routingKeyStamp->routingKey;
            }

            $amqpEnvelope = [
                'body' => $body,
                'headers' => $headers,
            ];

            // AMQP routing key для publish
            if ($routingKeyStamp instanceof AMQPRoutingKeyStamp) {
                $amqpEnvelope['routing_key'] = $routingKeyStamp->routingKey;
            }

            return $amqpEnvelope;
        } catch (\Throwable $exception) {
            $this->logger->error('AmqpMessengerSerializer::encode failed', [
                'message_class' => $message::class,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
