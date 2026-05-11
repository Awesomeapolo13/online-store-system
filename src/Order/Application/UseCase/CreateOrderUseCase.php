<?php

declare(strict_types=1);

namespace App\Order\Application\UseCase;

use App\Order\Application\Command\CreateOrder\CreateOrderCommand;
use App\Order\Application\Query\GetCart\GetCartQuery;
use App\Order\Application\Request\CreateOrderRequest;
use App\Order\Domain\Exception\DomainException;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Application\Query\QueryBusInterface;

final readonly class CreateOrderUseCase
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(CreateOrderRequest $request): void
    {
        $cart = $this->queryBus->execute(
            new GetCartQuery($request->userId, $request->region),
        );

        if ($cart === null || $cart->getCartItems()->isEmpty()) {
            throw new DomainException('В вашей корзину отсутствуют продукты для оформления заказа.');
        }

        $this->commandBus->dispatch(
            new CreateOrderCommand(
                userId: $request->userId,
                regionCode: $request->region,
            ),
        );
    }
}
