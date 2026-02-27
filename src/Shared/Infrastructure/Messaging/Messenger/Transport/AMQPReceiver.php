<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Messenger\Transport;

use App\Shared\Infrastructure\Messaging\Messenger\Stamp\AMQPMessageStamp;
use App\Shared\Infrastructure\Messaging\RabbitMQ\Connection\AMQPRabbitMQConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class AMQPReceiver implements ReceiverInterface
{
    private ?AMQPChannel $channel = null;
    private array $receivedEnvelopes = [];
    private bool $isConsuming = false;

    public function __construct(
        private readonly AMQPRabbitMQConnection $connectionManager,
        private readonly SerializerInterface $serializer,
        private readonly array $queueNames,
        private readonly LoggerInterface $logger,
        private readonly int $prefetchCount = 10,
    ) {
    }

    public function get(): iterable
    {
        $this->setupChannel();
        $this->startConsuming();

        // Ждем сообщения
        while ($this->channel->is_consuming()) {
            try {
                $this->channel->wait();

                // Отдаем полученные сообщения
                if (!empty($this->receivedEnvelopes)) {
                    $envelopes = $this->receivedEnvelopes;
                    $this->receivedEnvelopes = [];

                    foreach ($envelopes as $envelope) {
                        yield $envelope;
                    }
                }
            } catch (\Throwable $exception) {
                $this->logger->error('Error while consuming messages', [
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);

                throw new TransportException($exception->getMessage(), 0, $exception);
            }
        }
    }

    public function ack(Envelope $envelope): void
    {
        $stamp = $envelope->last(AMQPMessageStamp::class);

        if (!$stamp instanceof AMQPMessageStamp) {
            throw new TransportException('Envelope is missing AMQPMessageStamp');
        }

        try {
            $this->channel?->basic_ack($stamp->deliveryTag);

            $this->logger->debug('Message acknowledged', [
                'delivery_tag' => $stamp->deliveryTag,
            ]);
        } catch (\Throwable $exception) {
            throw new TransportException('Failed to ack message: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function reject(Envelope $envelope): void
    {
        $stamp = $envelope->last(AMQPMessageStamp::class);

        if (!$stamp instanceof AMQPMessageStamp) {
            throw new TransportException('Envelope is missing AmqpMessageStamp');
        }

        try {
            // reject с requeue=false - сообщение отправится в DLX
            $this->channel?->basic_reject($stamp->deliveryTag, false);

            $this->logger->warning('Message rejected', [
                'delivery_tag' => $stamp->deliveryTag,
            ]);
        } catch (\Throwable $exception) {
            throw new TransportException('Failed to reject message: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws \Exception
     */
    private function setUpChannel(): void
    {
        if ($this->channel !== null) {
            return;
        }

        $this->channel = $this->connectionManager->getChannel();
        // Задаем prefetch count
        $this->channel->basic_qos(
            prefetch_size: 0,
            prefetch_count: $this->prefetchCount,
            a_global: false
        );

        $this->logger->info('AMQP channel created', [
            'prefetch_count' => $this->prefetchCount,
        ]);
    }

    private function startConsuming(): void
    {
        if ($this->isConsuming) {
            return;
        }

        foreach ($this->queueNames as $queueName) {
            $this->logger->info('Starting consume queue', [
                'queue_name' => $queueName,
            ]);

            $this->channel->basic_consume(
                queue: $queueName,
                consumer_tag: '',
                callback: function (AMQPMessage $message) use ($queueName): void {
                    $this->onMessageReceived($message, $queueName);
                }
            );
        }

        $this->isConsuming = true;

        $this->logger->info('Consumer registered successfully', [
            'queues' => $this->queueNames,
        ]);
    }

    private function onMessageReceived(AMQPMessage $message, string $queueName): void
    {
        try {
            $this->logger->debug('Message received', [
                'queue' => $queueName,
                'delivery_tag' => $message->getDeliveryTag(),
            ]);
            // Десериализуем сообщение
            $envelope = $this->serializer->decode([
                'body' => $message->getBody(),
                'headers' => $message->get_properties(),
            ]);
            // Добавляем stamps
            $envelope = $envelope
                ->with(new AMQPMessageStamp(
                    deliveryTag: $message->getDeliveryTag(),
                    queueName: $queueName,
                ))
                ->with(new TransportMessageIdStamp($message->getDeliveryTag()));
            $this->receivedEnvelopes[] = $envelope;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to process received message', [
                'error' => $exception->getMessage(),
                'queue' => $queueName,
            ]);

            // Отклоняем сообщение при ошибке десериализации
            $message->nack(requeue: false);
        }
    }

    public function __destruct()
    {
        if ($this->channel !== null) {
            try {
                $this->channel->close();
            } catch (\Throwable $exception) {
                // Ignore
            }
        }
    }
}
