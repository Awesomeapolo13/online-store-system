<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class AMQPTransport implements TransportInterface
{
    public function __construct(
        private AMQPReceiver $receiver,
        private AMQPSender $sender,
    ) {
    }

    public function get(): iterable
    {
        return $this->receiver->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->receiver->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->receiver->reject($envelope);
    }

    /**
     * @throws \Exception
     */
    public function send(Envelope $envelope): Envelope
    {
        return $this->sender->send($envelope);
    }
}
