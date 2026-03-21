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

    public function resolveForCommand(CommandInterface $command): ?string
    {
        $class = $command::class;

        if (!isset($this->commandRoutingMap[$class])) {
            return null;
        }

        return $this->commandRoutingMap[$class];
    }

    public function resolveForEvent(DomainEventInterface $event): ?string
    {
        $class = $event::class;

        if (!isset($this->eventRoutingMap[$class])) {
            return null;
        }

        return $this->eventRoutingMap[$class];
    }
}
