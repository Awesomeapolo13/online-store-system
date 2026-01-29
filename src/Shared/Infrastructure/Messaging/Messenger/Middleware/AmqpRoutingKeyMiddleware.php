<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Messenger\Middleware;

use App\Shared\Application\Command\CommandInterface;
use App\Shared\Domain\Event\EventInterface as DomainEventInterface;
use App\Shared\Infrastructure\Messaging\Messenger\Stamp\AmqpRoutingKeyStamp;
use App\Shared\Infrastructure\Messaging\RabbitMQ\Routing\RoutingKeyResolver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final readonly class AmqpRoutingKeyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RoutingKeyResolver $routingKeyResolver,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if (null === $envelope->last(TransportMessageIdStamp::class)) {
            $routingKey = match (true) {
                $message instanceof CommandInterface => $this->routingKeyResolver->resolveForCommand($message),
                $message instanceof DomainEventInterface => $this->routingKeyResolver->resolveForEvent($message),
                default => null,
            };

            if ($routingKey !== null) {
                $envelope = $envelope->with(new AmqpRoutingKeyStamp($routingKey));
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
