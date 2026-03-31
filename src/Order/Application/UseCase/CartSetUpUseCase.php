<?php

declare(strict_types=1);

namespace App\Order\Application\UseCase;

use App\Order\Application\Assembler\CartAssembler;
use App\Order\Application\Command\CartSetUp\CartSetUpCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Query\GetCart\GetCartQuery;
use App\Order\Application\Request\CartSetUpRequest;
use App\Order\Application\Response\CartResponse;
use App\Order\Domain\Exception\DomainException;
use App\Order\Domain\ValueObject\Region;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Application\Query\QueryBusInterface;

final readonly class CartSetUpUseCase
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
        private CartAssembler $assembler,
    ) {
    }

    public function __invoke(CartSetUpRequest $request): CartResponse
    {
        $cart = $this->queryBus->execute(
            new GetCartQuery($request->userId, $request->region),
        );

        if ($cart === null) {
            $this->commandBus->dispatch(
                new CreateNewCartCommand($request->userId, new Region($request->region)),
            );

            throw new DomainException('Cart is not ready yet. Please retry the request.');
        }

        if (!$request->isDelivery && $request->shopId === null) {
            throw new DomainException('shop_id is required when is_delivery is false.');
        }

        $orderDate = new \DateTimeImmutable($request->orderDate);

        $this->commandBus->dispatch(
            new CartSetUpCommand(
                userId: $request->userId,
                regionCode: $request->region,
                isDelivery: $request->isDelivery,
                orderDate: $orderDate,
                shopId: $request->shopId,
            ),
        );

        $updatedCart = $this->queryBus->execute(
            new GetCartQuery($request->userId, $request->region),
        );

        return $this->assembler->toResponse($updatedCart);
    }
}
