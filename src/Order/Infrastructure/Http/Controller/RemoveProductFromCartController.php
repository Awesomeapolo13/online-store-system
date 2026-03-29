<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Request\RemoveProductFromCartRequest;
use App\Order\Application\UseCase\RemoveProductFromCartUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RemoveProductFromCartController
{
    public function __construct(
        private RemoveProductFromCartUseCase $useCase,
    ) {
    }

    #[Route('/api/v1/cart/remove', name: 'remove_product_from_cart', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload]
        RemoveProductFromCartRequest $request,
    ): Response {
        $result = ($this->useCase)($request);

        return new JsonResponse(['cart' => $result], Response::HTTP_OK);
    }
}
