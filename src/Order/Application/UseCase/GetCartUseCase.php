<?php

declare(strict_types=1);

namespace App\Order\Application\UseCase;

use App\Order\Application\Assembler\CartAssembler;
use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Query\GetCart\GetCartQuery;
use App\Order\Application\Request\GetCartRequest;
use App\Order\Application\Response\CartResponse;
use App\Order\Domain\ValueObject\Region;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Application\Query\QueryBusInterface;

final readonly class GetCartUseCase
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
        private CartAssembler $assembler,
    ) {
    }

    public function __invoke(GetCartRequest $request): ?CartResponse
    {
        $cart = $this->queryBus->execute(
            new GetCartQuery($request->userId, $request->region),
        );

        if ($cart === null) {
            $this->commandBus->execute(
                new CreateNewCartCommand($request->userId, new Region($request->region)),
            );

            return null;
        }

        return $this->assembler->toResponse($cart);
    }
}
