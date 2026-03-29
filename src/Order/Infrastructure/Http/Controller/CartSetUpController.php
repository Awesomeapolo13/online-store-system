<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Request\CartSetUpRequest;
use App\Order\Application\UseCase\CartSetUpUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CartSetUpController
{
    public function __construct(
        private CartSetUpUseCase $useCase,
    ) {
    }

    #[Route('/api/v1/cart/setup', name: 'cart_setup', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload]
        CartSetUpRequest $request,
    ): Response {
        $result = ($this->useCase)($request);

        return new JsonResponse(['cart' => $result], Response::HTTP_OK);
    }
}
