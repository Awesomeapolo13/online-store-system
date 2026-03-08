<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Request\GetCartRequest;
use App\Order\Application\UseCase\GetCartUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetCartController
{
    public function __construct(
        private GetCartUseCase $useCase,
    ) {
    }

    #[Route('/api/v1/cart', name: 'get_cart', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString]
        GetCartRequest $basketRequest,
    ): Response {
        $result = ($this->useCase)($basketRequest);

        if ($result === null) {
            return new JsonResponse(null, Response::HTTP_ACCEPTED);
        }

        return new JsonResponse($result, Response::HTTP_OK);
    }
}
