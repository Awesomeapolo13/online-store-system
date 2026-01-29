<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Messenger\Serializer;

use App\Shared\Infrastructure\Messaging\Messenger\Stamp\AmqpRoutingKeyStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

final readonly class AmqpMessengerSerializer implements SerializerInterface
{
    public function __construct(
        private SymfonySerializerInterface $serializer,
    ) {
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $body = $encodedEnvelope['body'];
        $headers = $encodedEnvelope['headers'] ?? [];

        $type = $headers['type'] ?? null;

        if ($type === null) {
            throw new \InvalidArgumentException('Missing message type header');
        }

        $message = $this->serializer->deserialize(
            $body,
            $type,
            'json'
        );

        $envelope = new Envelope($message);

        // Восстанавливаем routing key из headers
        if (isset($headers['routing_key'])) {
            $envelope = $envelope->with(
                new AmqpRoutingKeyStamp($headers['routing_key'])
            );
        }

        return $envelope;
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        $body = $this->serializer->serialize($message, 'json');

        $headers = [
            'type' => $message::class,
            'content_type' => 'application/json',
        ];

        // Добавляем routing key в headers
        $routingKeyStamp = $envelope->last(AmqpRoutingKeyStamp::class);
        if ($routingKeyStamp instanceof AmqpRoutingKeyStamp) {
            $headers['routing_key'] = $routingKeyStamp->routingKey;
        }

        $amqpEnvelope = [
            'body' => $body,
            'headers' => $headers,
        ];

        // AMQP routing key для publish
        if ($routingKeyStamp instanceof AmqpRoutingKeyStamp) {
            $amqpEnvelope['routing_key'] = $routingKeyStamp->routingKey;
        }

        return $amqpEnvelope;
    }
}
