<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Controller;

use App\Order\Application\Request\GetCartRequest;
use App\Order\Application\UseCase\GetCartUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class GetCartController
{
    public function __construct(
        private ValidatorInterface $validator,
        private GetCartUseCase $useCase,
    ) {
    }

    #[Route('/api/v1/cart', name: 'get_cart', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $basketRequest = new GetCartRequest($request);

        $violations = $this->validator->validate($basketRequest);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = ($this->useCase)($basketRequest);

        if ($result === null) {
            return new Response(null, Response::HTTP_ACCEPTED);
        }

        return new JsonResponse($result, Response::HTTP_OK);
    }
}
