<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Response\InventoryMovementResponse;
use App\Entity\InventoryMovement;
use App\Entity\InventoryReason;
use App\Entity\InventoryReservation;
use App\Entity\Paca;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventoryReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class InventoryManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private InventoryMovementRepository $movementRepo,
        private InventoryReservationRepository $reservationRepo,
        private Paginator $paginator,
    ) {
    }

    /**
     * Records a movement and updates Paca.stock atomically.
     * If movementType is IN: paca.stock += quantity, set qtyIn=quantity
     * If movementType is OUT: validate stock >= quantity, paca.stock -= quantity, set qtyOut=quantity
     * Set balanceAfter = paca.stock (after update)
     * Throws ConflictHttpException if OUT and insufficient stock
     */
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
    ): InventoryMovement {
        $movementType = $reason->getDirection();

        $actualQty = $quantity;

        if ($movementType === 'OUT') {
            if (!$forceAdjustment && $paca->getStock() < $quantity) {
                throw new ConflictHttpException(
                    sprintf(
                        'Stock insuficiente para paca %s. Stock actual: %d, requerido: %d',
                        $paca->getCode(),
                        $paca->getStock(),
                        $quantity,
                    ),
                );
            }
            if ($forceAdjustment && $paca->getStock() < $quantity) {
                $actualQty = $paca->getStock();
                $notes = sprintf('%s [Stock insuficiente: se ajustaron %d de %d unidades]', $notes ?? '', $actualQty, $quantity);
            }
            $paca->setStock($paca->getStock() - $actualQty);
        } else {
            $paca->setStock($paca->getStock() + $actualQty);
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
        $movement->setBalanceAfter($paca->getStock());
        $movement->setUnitCost($unitCost);
        $movement->setNotes($notes);

        $this->em->persist($movement);
        $this->em->flush();

        return $movement;
    }

    public function getCurrentBalance(Paca $paca): int
    {
        return $paca->getStock();
    }

    public function getAvailableStock(Paca $paca): int
    {
        $reserved = $this->reservationRepo->getActiveReservedQuantity($paca->getId());
        return $paca->getStock() - $reserved;
    }

    public function reserveStock(
        Paca $paca,
        User $user,
        int $quantity,
        ?int $salesOrderId = null,
        ?int $salesOrderItemId = null,
        ?\DateTimeImmutable $expiresAt = null,
    ): InventoryReservation {
        $available = $this->getAvailableStock($paca);
        if ($available < $quantity) {
            throw new ConflictHttpException(
                sprintf(
                    'Stock disponible insuficiente para paca %s. Disponible: %d, requerido: %d',
                    $paca->getCode(),
                    $available,
                    $quantity,
                ),
            );
        }

        $reservation = new InventoryReservation();
        $reservation->setPaca($paca);
        $reservation->setUser($user);
        $reservation->setQuantity($quantity);
        $reservation->setSalesOrderId($salesOrderId);
        $reservation->setSalesOrderItemId($salesOrderItemId);
        $reservation->setExpiresAt($expiresAt);

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    public function releaseReservation(InventoryReservation $reservation): void
    {
        $reservation->setStatus('RELEASED');
        $this->em->flush();
    }

    public function fulfillReservation(InventoryReservation $reservation): void
    {
        $reservation->setStatus('FULFILLED');
        $this->em->flush();
    }

    public function expireReservations(): int
    {
        $now = new \DateTimeImmutable();
        $qb = $this->reservationRepo->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.expiresAt IS NOT NULL')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('now', $now);

        $reservations = $qb->getQuery()->getResult();
        $count = 0;

        foreach ($reservations as $reservation) {
            $reservation->setStatus('EXPIRED');
            $count++;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
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
