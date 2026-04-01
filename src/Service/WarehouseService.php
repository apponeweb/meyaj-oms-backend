<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateWarehouseRequest;
use App\DTO\Request\UpdateWarehouseRequest;
use App\DTO\Response\WarehouseResponse;
use App\Entity\Warehouse;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class WarehouseService
{
    public function __construct(
        private EntityManagerInterface $em,
        private WarehouseRepository $warehouseRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->warehouseRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            companyId: $pagination->companyId,
            active: $pagination->active,
            warehouseType: $pagination->warehouseType ?? null,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (Warehouse $w) => new WarehouseResponse($w),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): WarehouseResponse
    {
        $warehouse = $this->warehouseRepository->find($id);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Bodega con ID %d no encontrada.', $id));
        }
        return new WarehouseResponse($warehouse);
    }

    public function create(CreateWarehouseRequest $request): WarehouseResponse
    {
        $company = $this->em->getRepository(\App\Entity\Company::class)->find($request->companyId);
        if ($company === null) {
            throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $request->companyId));
        }

        $warehouse = new Warehouse();
        $warehouse->setCompany($company);
        $warehouse->setCode($request->code);
        $warehouse->setName($request->name);
        $warehouse->setWarehouseType($request->warehouseType);
        $warehouse->setAddress($request->address);
        $warehouse->setMonthlyCost($request->monthlyCost);
        $warehouse->setIsExternal($request->isExternal);

        $this->em->persist($warehouse);
        $this->em->flush();

        return new WarehouseResponse($warehouse);
    }

    public function update(int $id, UpdateWarehouseRequest $request): WarehouseResponse
    {
        $warehouse = $this->warehouseRepository->find($id);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Bodega con ID %d no encontrada.', $id));
        }

        if ($request->companyId !== null) {
            $company = $this->em->getRepository(\App\Entity\Company::class)->find($request->companyId);
            if ($company === null) {
                throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $request->companyId));
            }
            $warehouse->setCompany($company);
        }

        if ($request->code !== null) $warehouse->setCode($request->code);
        if ($request->name !== null) $warehouse->setName($request->name);
        if ($request->warehouseType !== null) $warehouse->setWarehouseType($request->warehouseType);
        if ($request->address !== null) $warehouse->setAddress($request->address);
        if ($request->monthlyCost !== null) $warehouse->setMonthlyCost($request->monthlyCost);
        if ($request->isExternal !== null) $warehouse->setIsExternal($request->isExternal);
        if ($request->isActive !== null) $warehouse->setIsActive($request->isActive);

        $this->em->flush();

        return new WarehouseResponse($warehouse);
    }

    public function delete(int $id): void
    {
        $warehouse = $this->warehouseRepository->find($id);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Bodega con ID %d no encontrada.', $id));
        }
        $this->em->remove($warehouse);
        $this->em->flush();
    }
}
