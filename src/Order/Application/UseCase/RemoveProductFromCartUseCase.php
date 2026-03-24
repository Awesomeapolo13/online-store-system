<?php

declare(strict_types=1);

namespace App\Order\Application\UseCase;

use App\Order\Application\Assembler\CartAssembler;
use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\RemoveProductFromCart\RemoveProductFromCartCommand;
use App\Order\Application\Query\GetCart\GetCartQuery;
use App\Order\Application\Request\RemoveProductFromCartRequest;
use App\Order\Application\Response\CartResponse;
use App\Order\Domain\Exception\DomainException;
use App\Order\Domain\ValueObject\Region;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Application\Query\QueryBusInterface;

final readonly class RemoveProductFromCartUseCase
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
        private CartAssembler $assembler,
    ) {
    }

    public function __invoke(RemoveProductFromCartRequest $request): CartResponse
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

        $this->commandBus->dispatch(
            new RemoveProductFromCartCommand(
                userId: $request->userId,
                regionCode: $request->region,
                supCode: $request->supCode,
            ),
        );

        $updatedCart = $this->queryBus->execute(
            new GetCartQuery($request->userId, $request->region),
        );

        return $this->assembler->toResponse($updatedCart);
    }
}
