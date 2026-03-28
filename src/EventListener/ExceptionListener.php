<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'Error interno del servidor.';
        $errors = [];

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();

            $previous = $exception->getPrevious();
            if ($previous instanceof ValidationFailedException) {
                $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
                $message = 'Error de validación.';

                foreach ($previous->getViolations() as $violation) {
                    $errors[] = [
                        'field' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage(),
                    ];
                }
            }
        }

        $payload = [
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ],
        ];

        if (!empty($errors)) {
            $payload['error']['errors'] = $errors;
        }

        $response = new JsonResponse($payload, $statusCode);
        $event->setResponse($response);
    }
}
