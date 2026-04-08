<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Response\InventoryMovementResponse;
use App\Entity\InventoryMovement;
use App\Entity\InventoryReason;
use App\Entity\Paca;
use App\Entity\PacaUnit;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventoryMovementRepository;
use App\Repository\PacaUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class InventoryManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private InventoryMovementRepository $movementRepo,
        private PacaUnitRepository $pacaUnitRepo,
        private Paginator $paginator,
    ) {
    }

    public function recordMovement(
        Paca $paca,
        Warehouse $warehouse,
        ?WarehouseBin $bin,
        InventoryReason $reason,
        User $user,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $unitCost = null,
        ?string $notes = null,
        bool $forceAdjustment = false,
        ?PacaUnit $pacaUnit = null,
    ): InventoryMovement {
        $movementType = $reason->getDirection();
        $actualQty = $quantity;

        if ($movementType === 'OUT') {
            if (!$forceAdjustment && $paca->getCachedStock() < $quantity) {
                throw new ConflictHttpException(
                    sprintf(
                        'Stock insuficiente para paca %s. Stock actual: %d, requerido: %d',
                        $paca->getCode(),
                        $paca->getCachedStock(),
                        $quantity,
                    ),
                );
            }
            if ($forceAdjustment && $paca->getCachedStock() < $quantity) {
                $actualQty = $paca->getCachedStock();
                $notes = sprintf('%s [Stock insuficiente: se ajustaron %d de %d unidades]', $notes ?? '', $actualQty, $quantity);
            }
            $paca->setCachedStock($paca->getCachedStock() - $actualQty);
        } else {
            $paca->setCachedStock($paca->getCachedStock() + $actualQty);
        }

        $movement = new InventoryMovement();
        $movement->setCompany($warehouse->getCompany());
        $movement->setPaca($paca);
        $movement->setWarehouse($warehouse);
        $movement->setWarehouseBin($bin);
        $movement->setReason($reason);
        $movement->setUser($user);
        $movement->setMovementType($movementType);
        $movement->setReferenceType($referenceType);
        $movement->setReferenceId($referenceId);
        $movement->setQtyIn($movementType === 'IN' ? $actualQty : 0);
        $movement->setQtyOut($movementType === 'OUT' ? $actualQty : 0);
        $movement->setBalanceAfter($paca->getCachedStock());
        $movement->setUnitCost($unitCost);
        $movement->setNotes($notes);
        if ($pacaUnit !== null) {
            $movement->setPacaUnit($pacaUnit);
        }

        $this->em->persist($movement);
        $this->em->flush();

        return $movement;
    }

    public function getCurrentBalance(Paca $paca): int
    {
        return $paca->getCachedStock();
    }

    public function getAvailableStock(Paca $paca): int
    {
        return $this->pacaUnitRepo->countAvailableByPaca($paca->getId());
    }

    /**
     * Reserve N available PacaUnits for a sales order item.
     * Uses atomic UPDATE to prevent race conditions.
     *
     * @return PacaUnit[] The reserved units
     */
    public function reserveStock(
        Paca $paca,
        SalesOrder $salesOrder,
        SalesOrderItem $salesOrderItem,
        int $quantity,
    ): array {
        $available = $this->pacaUnitRepo->findAvailableByPaca($paca->getId(), $quantity);

        if (count($available) < $quantity) {
            throw new ConflictHttpException(
                sprintf(
                    'Stock disponible insuficiente para paca %s. Disponible: %d, requerido: %d',
                    $paca->getCode(),
                    count($available),
                    $quantity,
                ),
            );
        }

        foreach ($available as $unit) {
            $unit->setStatus(PacaUnit::STATUS_RESERVED);
            $unit->setSalesOrder($salesOrder);
            $unit->setSalesOrderItem($salesOrderItem);
        }

        $this->em->flush();

        return $available;
    }

    /**
     * Release all PacaUnits reserved for a specific sales order and paca.
     */
    public function releaseUnits(Paca $paca, SalesOrder $salesOrder): void
    {
        $units = $this->em->getRepository(PacaUnit::class)->findBy([
            'paca' => $paca,
            'salesOrder' => $salesOrder,
            'status' => PacaUnit::STATUS_RESERVED,
        ]);

        foreach ($units as $unit) {
            $unit->setStatus(PacaUnit::STATUS_AVAILABLE);
            $unit->setSalesOrder(null);
            $unit->setSalesOrderItem(null);
        }

        $this->em->flush();
    }

    /**
     * Release all units for all items in a sales order.
     */
    public function releaseAllUnitsForOrder(SalesOrder $salesOrder): void
    {
        $units = $this->em->getRepository(PacaUnit::class)->findBy([
            'salesOrder' => $salesOrder,
        ]);

        foreach ($units as $unit) {
            if (in_array($unit->getStatus(), [PacaUnit::STATUS_RESERVED, PacaUnit::STATUS_PICKED], true)) {
                $unit->setStatus(PacaUnit::STATUS_AVAILABLE);
                $unit->setSalesOrder(null);
                $unit->setSalesOrderItem(null);
            }
        }

        $this->em->flush();
    }

    /**
     * Recalculate and update the cached stock for a paca.
     */
    public function updateCachedStock(Paca $paca): void
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(pu.id)')
            ->from(PacaUnit::class, 'pu')
            ->where('pu.paca = :paca')
            ->andWhere('pu.status IN (:statuses)')
            ->setParameter('paca', $paca)
            ->setParameter('statuses', [
                PacaUnit::STATUS_AVAILABLE,
                PacaUnit::STATUS_RESERVED,
                PacaUnit::STATUS_PICKED,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        $paca->setCachedStock($count);
        $this->em->flush();
    }

    public function getKardex(int $pacaId, PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->movementRepo->createPaginatedQueryBuilder(pacaId: $pacaId);
        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (InventoryMovement $m) => new InventoryMovementResponse($m),
                $result->data,
            ),
            meta: $result->meta,
        );
    }
}
