<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Request\CreateOrderRequest;
use App\Order\Application\UseCase\CreateOrderUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CreateOrderController
{
    public function __construct(
        private CreateOrderUseCase $useCase,
    ) {
    }

    #[Route('/api/v1/order', name: 'order_create', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload]
        CreateOrderRequest $request,
    ): Response {
        ($this->useCase)($request);

        return new JsonResponse(null, Response::HTTP_OK);
    }
}
