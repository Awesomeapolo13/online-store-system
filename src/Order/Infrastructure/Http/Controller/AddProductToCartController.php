<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Request\AddProductToCartRequest;
use App\Order\Application\UseCase\AddProductToCartUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AddProductToCartController
{
    public function __construct(
        private AddProductToCartUseCase $useCase,
    ) {
    }

    #[Route('/api/v1/cart/add', name: 'add_product_to_cart', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload]
        AddProductToCartRequest $request,
    ): Response {
        $result = ($this->useCase)($request);

        return new JsonResponse(['cart' => $result], Response::HTTP_OK);
    }
}
