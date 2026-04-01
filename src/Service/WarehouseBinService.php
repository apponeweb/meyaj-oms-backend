<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateWarehouseBinRequest;
use App\DTO\Request\UpdateWarehouseBinRequest;
use App\DTO\Response\WarehouseBinResponse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\WarehouseBinRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class WarehouseBinService
{
    public function __construct(
        private EntityManagerInterface $em,
        private WarehouseBinRepository $binRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->binRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            warehouseId: $pagination->warehouseId ?? null,
            active: $pagination->active,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (WarehouseBin $b) => new WarehouseBinResponse($b),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): WarehouseBinResponse
    {
        $bin = $this->binRepository->find($id);
        if ($bin === null) {
            throw new NotFoundHttpException(sprintf('Ubicación con ID %d no encontrada.', $id));
        }
        return new WarehouseBinResponse($bin);
    }

    public function create(CreateWarehouseBinRequest $request): WarehouseBinResponse
    {
        $warehouse = $this->em->getRepository(\App\Entity\Warehouse::class)->find($request->warehouseId);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Bodega con ID %d no encontrada.', $request->warehouseId));
        }

        $bin = new WarehouseBin();
        $bin->setWarehouse($warehouse);
        $bin->setCode($request->code);
        $bin->setName($request->name);
        $bin->setZone($request->zone);
        $bin->setBinType($request->binType);
        $bin->setCapacity($request->capacity);

        $this->em->persist($bin);
        $this->em->flush();

        return new WarehouseBinResponse($bin);
    }

    public function update(int $id, UpdateWarehouseBinRequest $request): WarehouseBinResponse
    {
        $bin = $this->binRepository->find($id);
        if ($bin === null) {
            throw new NotFoundHttpException(sprintf('Ubicación con ID %d no encontrada.', $id));
        }

        if ($request->code !== null) $bin->setCode($request->code);
        if ($request->name !== null) $bin->setName($request->name);
        if ($request->zone !== null) $bin->setZone($request->zone);
        if ($request->binType !== null) $bin->setBinType($request->binType);
        if ($request->capacity !== null) $bin->setCapacity($request->capacity);
        if ($request->isActive !== null) $bin->setIsActive($request->isActive);

        $this->em->flush();

        return new WarehouseBinResponse($bin);
    }

    public function delete(int $id): void
    {
        $bin = $this->binRepository->find($id);
        if ($bin === null) {
            throw new NotFoundHttpException(sprintf('Ubicación con ID %d no encontrada.', $id));
        }
        $this->em->remove($bin);
        $this->em->flush();
    }
}
