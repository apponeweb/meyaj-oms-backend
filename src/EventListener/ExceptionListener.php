<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
        } elseif ($exception instanceof UniqueConstraintViolationException) {
            $statusCode = Response::HTTP_CONFLICT;
            $message = 'Ya existe un registro con estos datos.';
            
            // Mapeo opcional de nombres de índices a mensajes amigables
            if (str_contains($exception->getMessage(), 'uniq_branch_company_name')) {
                $message = 'Esta sucursal ya existe en esta empresa.';
            } elseif (str_contains($exception->getMessage(), 'uniq_department_branch_name')) {
                $message = 'Este departamento ya existe en esta sucursal.';
            } elseif (str_contains($exception->getMessage(), 'company.UNIQ_') || str_contains($exception->getMessage(), 'company.name')) {
                $message = 'Esta empresa ya existe.';
            } elseif (str_contains($exception->getMessage(), 'supplier.UNIQ_') || str_contains($exception->getMessage(), 'supplier.name')) {
                $message = 'Este proveedor ya existe.';
            } elseif (str_contains($exception->getMessage(), 'brand.UNIQ_') || str_contains($exception->getMessage(), 'brand.name')) {
                $message = 'Esta marca ya existe.';
            } elseif (str_contains($exception->getMessage(), 'label_catalog.name')) {
                $message = 'Esta etiqueta ya existe.';
            } elseif (str_contains($exception->getMessage(), 'quality_grade.name')) {
                $message = 'Esta calidad ya existe.';
            } elseif (str_contains($exception->getMessage(), 'season_catalog.name')) {
                $message = 'Esta temporada ya existe.';
            } elseif (str_contains($exception->getMessage(), 'gender_catalog.name')) {
                $message = 'Este género ya existe.';
            } elseif (str_contains($exception->getMessage(), 'garment_type.name')) {
                $message = 'Este tipo de prenda ya existe.';
            } elseif (str_contains($exception->getMessage(), 'fabric_type.name')) {
                $message = 'Este tipo de tela ya existe.';
            } elseif (str_contains($exception->getMessage(), 'size_profile.name')) {
                $message = 'Este perfil de talla ya existe.';
            }
        }

        $payload = [
            'error' => [
                'code' => $statusCode,
                'message' => $message,
                'details' => $exception->getMessage(),
            ],
        ];

        if (!empty($errors)) {
            $payload['error']['errors'] = $errors;
        }

        $response = new JsonResponse($payload, $statusCode);
        $event->setResponse($response);
    }
}
