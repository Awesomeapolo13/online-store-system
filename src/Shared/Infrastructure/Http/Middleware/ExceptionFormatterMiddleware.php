<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Middleware;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ExceptionFormatterMiddleware
{
    public function __construct(
        private readonly string $env,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($this->env === 'dev') {
            return;
        }

        // Unwrap HandlerFailedException to expose the original handler exception.
        if ($exception instanceof HandlerFailedException) {
            $wrapped = array_values($exception->getWrappedExceptions());
            if (!empty($wrapped)) {
                $exception = $wrapped[0];
            }
        }

        if ($exception->getPrevious() instanceof ValidationFailedException) {
            $response = $this->getWrongValidationResponse($exception->getPrevious());
            $event->setResponse($response);

            return;
        }

        $response = $this->getDefaultProdResponse($exception);
        $event->setResponse($response);
    }

    private function getWrongValidationResponse(ValidationFailedException $exception): JsonResponse
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[] = [
                'name' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return new JsonResponse(
            [
                'title' => 'Incorrect request data',
                'errors' => $errors,
            ],
            Response::HTTP_BAD_REQUEST,
        );
    }

    private function getDefaultProdResponse(\Throwable $throwable): JsonResponse
    {
        return new JsonResponse(
            [
                'message' => $throwable->getMessage(),
            ],
            Response::HTTP_BAD_REQUEST,
        );
    }
}
