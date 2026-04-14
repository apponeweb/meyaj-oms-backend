<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateInventoryReasonRequest;
use App\DTO\Request\UpdateInventoryReasonRequest;
use App\DTO\Response\InventoryReasonResponse;
use App\Entity\InventoryReason;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventoryReasonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class InventoryReasonService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InventoryReasonRepository $reasonRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->reasonRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            direction: $pagination->direction ?? null,
            active: $pagination->active,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (InventoryReason $r) => new InventoryReasonResponse($r),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): InventoryReasonResponse
    {
        $reason = $this->reasonRepository->find($id);
        if ($reason === null) {
            throw new NotFoundHttpException(sprintf('Motivo de inventario con ID %d no encontrado.', $id));
        }
        return new InventoryReasonResponse($reason);
    }

    public function create(CreateInventoryReasonRequest $request): InventoryReasonResponse
    {
        $reason = new InventoryReason();
        $reason->setCode($request->code);
        $reason->setName($request->name);
        $reason->setDirection($request->direction);
        $reason->setRequiresReference($request->requiresReference);

        $this->em->persist($reason);
        $this->em->flush();

        return new InventoryReasonResponse($reason);
    }

    public function update(int $id, UpdateInventoryReasonRequest $request): InventoryReasonResponse
    {
        $reason = $this->reasonRepository->find($id);
        if ($reason === null) {
            throw new NotFoundHttpException(sprintf('Motivo de inventario con ID %d no encontrado.', $id));
        }

        if ($request->code !== null && $request->code !== $reason->getCode()) {
            if (in_array($reason->getCode(), InventoryReason::SYSTEM_CODES, true)) {
                throw new BadRequestHttpException(
                    sprintf('El motivo "%s" es de sistema y su código no puede modificarse.', $reason->getCode())
                );
            }
            $reason->setCode($request->code);
        }
        if ($request->name !== null) $reason->setName($request->name);
        if ($request->direction !== null) $reason->setDirection($request->direction);
        if ($request->requiresReference !== null) $reason->setRequiresReference($request->requiresReference);
        if ($request->isActive !== null) $reason->setIsActive($request->isActive);

        $this->em->flush();

        return new InventoryReasonResponse($reason);
    }

    public function delete(int $id): void
    {
        $reason = $this->reasonRepository->find($id);
        if ($reason === null) {
            throw new NotFoundHttpException(sprintf('Motivo de inventario con ID %d no encontrado.', $id));
        }
        if (in_array($reason->getCode(), InventoryReason::SYSTEM_CODES, true)) {
            throw new BadRequestHttpException(
                sprintf('El motivo "%s" es de sistema y no puede eliminarse.', $reason->getCode())
            );
        }
        $this->em->remove($reason);
        $this->em->flush();
    }
}
