<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\RabbitMQ\Routing;

use App\Shared\Application\Command\CommandInterface;
use App\Shared\Domain\Event\EventInterface as DomainEventInterface;

final readonly class RoutingKeyResolver
{
    public function __construct(
        private array $commandRoutingMap,
        private array $eventRoutingMap,
    ) {
    }

    public function resolveForCommand(CommandInterface $command): string
    {
        $class = $command::class;

        if (!isset($this->commandRoutingMap[$class])) {
            throw new \InvalidArgumentException(sprintf('No routing key configured for command "%s"', $class));
        }

        return $this->commandRoutingMap[$class];
    }

    public function resolveForEvent(DomainEventInterface $event): string
    {
        $class = $event::class;

        if (!isset($this->eventRoutingMap[$class])) {
            throw new \InvalidArgumentException(sprintf('No routing key configured for event "%s"', $class));
        }

        return $this->eventRoutingMap[$class];
    }
}
