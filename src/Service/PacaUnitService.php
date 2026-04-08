<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Response\PacaUnitResponse;
use App\Entity\Paca;
use App\Entity\PacaUnit;
use App\Entity\Warehouse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\PacaUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PacaUnitService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PacaUnitRepository $pacaUnitRepo,
        private Paginator $paginator,
    ) {}

    public function list(
        PaginationRequest $pagination,
        ?int $pacaId = null,
        ?int $warehouseId = null,
        ?int $warehouseBinId = null,
        ?string $status = null,
        ?int $salesOrderId = null,
        ?int $purchaseOrderId = null,
    ): PaginatedResponse {
        $qb = $this->pacaUnitRepo->createPaginatedQueryBuilder(
            $pagination->search,
            $pacaId,
            $warehouseId,
            $warehouseBinId,
            $status,
            $salesOrderId,
            $purchaseOrderId,
        );
        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (PacaUnit $u) => new PacaUnitResponse($u),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): PacaUnitResponse
    {
        $u = $this->pacaUnitRepo->find($id);
        if ($u === null) throw new NotFoundHttpException(\sprintf('Unidad de paca con ID %d no encontrada.', $id));
        return new PacaUnitResponse($u);
    }

    public function findBySerial(string $serial): PacaUnitResponse
    {
        $u = $this->pacaUnitRepo->findBySerial($serial);
        if ($u === null) throw new NotFoundHttpException(\sprintf('Unidad de paca con serial "%s" no encontrada.', $serial));
        return new PacaUnitResponse($u);
    }

    /**
     * @return PacaUnit[]
     */
    public function createBatch(Paca $paca, Warehouse $warehouse, ?WarehouseBin $bin, int $quantity): array
    {
        $units = [];

        for ($i = 0; $i < $quantity; $i++) {
            $serial = $this->generateSerial($paca);

            $unit = new PacaUnit();
            $unit->setPaca($paca);
            $unit->setWarehouse($warehouse);
            $unit->setWarehouseBin($bin);
            $unit->setSerial($serial);
            $unit->setStatus(PacaUnit::STATUS_AVAILABLE);

            $this->em->persist($unit);
            $units[] = $unit;
        }

        $this->em->flush();

        return $units;
    }

    public function move(int $id, Warehouse $warehouse, ?WarehouseBin $bin): PacaUnitResponse
    {
        $u = $this->pacaUnitRepo->find($id);
        if ($u === null) throw new NotFoundHttpException(\sprintf('Unidad de paca con ID %d no encontrada.', $id));

        if (!$u->isAvailable()) {
            throw new BadRequestHttpException(\sprintf(
                'Solo se pueden mover unidades con estatus AVAILABLE. Estatus actual: %s.',
                $u->getStatus(),
            ));
        }

        $u->setWarehouse($warehouse);
        $u->setWarehouseBin($bin);
        $this->em->flush();

        return new PacaUnitResponse($u);
    }

    public function generateSerial(Paca $paca): string
    {
        $count = (int) $this->pacaUnitRepo->createQueryBuilder('pu')
            ->select('COUNT(pu.id)')
            ->where('pu.paca = :paca')
            ->setParameter('paca', $paca)
            ->getQuery()
            ->getSingleScalarResult();

        return \sprintf('%s-%04d', $paca->getCode(), $count + 1);
    }
}
