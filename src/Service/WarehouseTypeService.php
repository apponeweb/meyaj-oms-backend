<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateWarehouseTypeRequest;
use App\DTO\Request\UpdateWarehouseTypeRequest;
use App\DTO\Response\WarehouseTypeResponse;
use App\Entity\WarehouseType;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\WarehouseTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class WarehouseTypeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private WarehouseTypeRepository $warehouseTypeRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->warehouseTypeRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            active: $pagination->active,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (WarehouseType $wt) => new WarehouseTypeResponse($wt),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): WarehouseTypeResponse
    {
        $warehouseType = $this->warehouseTypeRepository->find($id);
        if ($warehouseType === null) {
            throw new NotFoundHttpException(\sprintf('Tipo de bodega con ID %d no encontrado.', $id));
        }
        return new WarehouseTypeResponse($warehouseType);
    }

    public function create(CreateWarehouseTypeRequest $request): WarehouseTypeResponse
    {
        $warehouseType = new WarehouseType();
        $warehouseType->setName($request->name);
        $warehouseType->setDescription($request->description);

        $this->em->persist($warehouseType);
        $this->em->flush();

        return new WarehouseTypeResponse($warehouseType);
    }

    public function update(int $id, UpdateWarehouseTypeRequest $request): WarehouseTypeResponse
    {
        $warehouseType = $this->warehouseTypeRepository->find($id);
        if ($warehouseType === null) {
            throw new NotFoundHttpException(\sprintf('Tipo de bodega con ID %d no encontrado.', $id));
        }

        if ($request->name !== null) $warehouseType->setName($request->name);
        if ($request->description !== null) $warehouseType->setDescription($request->description);
        if ($request->isActive !== null) $warehouseType->setIsActive($request->isActive);

        $this->em->flush();

        return new WarehouseTypeResponse($warehouseType);
    }

    public function delete(int $id): void
    {
        $warehouseType = $this->warehouseTypeRepository->find($id);
        if ($warehouseType === null) {
            throw new NotFoundHttpException(\sprintf('Tipo de bodega con ID %d no encontrado.', $id));
        }
        $this->em->remove($warehouseType);
        $this->em->flush();
    }
}
