<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Response\PacaUnitResponse;
use App\Entity\InventoryMovement;
use App\Entity\InventoryReason;
use App\Entity\Paca;
use App\Entity\PacaUnit;
use App\Entity\User;
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
        private InventoryManager $inventoryManager,
    ) {}

    /**
     * @param int[]|null $pacaIds
     */
    public function list(
        PaginationRequest $pagination,
        ?int $pacaId = null,
        ?int $warehouseId = null,
        ?int $warehouseBinId = null,
        ?string $status = null,
        ?int $salesOrderId = null,
        ?int $purchaseOrderId = null,
        ?bool $labeled = null,
        ?array $pacaIds = null,
    ): PaginatedResponse {
        $qb = $this->pacaUnitRepo->createPaginatedQueryBuilder(
            $pagination->search,
            $pacaId,
            $warehouseId,
            $warehouseBinId,
            $status,
            $salesOrderId,
            $purchaseOrderId,
            $labeled,
            $pacaIds,
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

    /**
     * @param int[] $ids
     * @return array{transferred: int, skipped: int, message: string}
     */
    public function transferBulk(
        array $ids,
        Warehouse $destinationWarehouse,
        ?WarehouseBin $destinationBin,
        InventoryReason $reason,
        User $user,
    ): array {
        if (empty($ids)) {
            throw new BadRequestHttpException('Debe proporcionar al menos un ID para traspasar.');
        }

        $units = $this->pacaUnitRepo->createQueryBuilder('u')
            ->leftJoin('u.paca', 'p')->addSelect('p')
            ->leftJoin('u.warehouse', 'w')->addSelect('w')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', array_values(array_unique(array_map('intval', $ids))))
            ->getQuery()
            ->getResult();

        if (empty($units)) {
            throw new NotFoundHttpException('No se encontraron unidades para traspasar.');
        }

        $transferOutReason = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => InventoryReason::CODE_TRANSFER_OUT]);
        $transferInReason = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => InventoryReason::CODE_TRANSFER_IN]);

        if ($transferOutReason === null || $transferInReason === null) {
            throw new BadRequestHttpException('No están configurados los motivos de traspaso de inventario.');
        }

        $transferred = 0;
        $skipped = 0;
        $affectedPairs = [];
        $movementGroups = [];

        foreach ($units as $unit) {
            if (!$unit instanceof PacaUnit) {
                continue;
            }

            if (!$unit->isAvailable()) {
                $skipped++;
                continue;
            }

            $sourceWarehouse = $unit->getWarehouse();
            if ($sourceWarehouse->getId() === $destinationWarehouse->getId()) {
                $sameBin = ($unit->getWarehouseBin()?->getId() ?? null) === ($destinationBin?->getId() ?? null);
                if ($sameBin) {
                    $skipped++;
                    continue;
                }
            }

            $sourceKey = sprintf('%d-%d', $unit->getPaca()->getId(), $sourceWarehouse->getId());
            $destinationKey = sprintf('%d-%d', $unit->getPaca()->getId(), $destinationWarehouse->getId());

            if (!isset($movementGroups[$sourceKey])) {
                $movementGroups[$sourceKey] = [
                    'paca' => $unit->getPaca(),
                    'warehouse' => $sourceWarehouse,
                    'quantityOut' => 0,
                ];
            }
            if (!isset($movementGroups[$destinationKey])) {
                $movementGroups[$destinationKey] = [
                    'paca' => $unit->getPaca(),
                    'warehouse' => $destinationWarehouse,
                    'quantityIn' => 0,
                ];
            }

            $movementGroups[$sourceKey]['quantityOut'] = ($movementGroups[$sourceKey]['quantityOut'] ?? 0) + 1;
            $movementGroups[$destinationKey]['quantityIn'] = ($movementGroups[$destinationKey]['quantityIn'] ?? 0) + 1;

            $unit->setWarehouse($destinationWarehouse);
            $unit->setWarehouseBin($destinationBin);
            $transferred++;

            $affectedPairs[$sourceKey] = [$unit->getPaca(), $sourceWarehouse];
            $affectedPairs[$destinationKey] = [$unit->getPaca(), $destinationWarehouse];
        }

        $this->em->flush();

        foreach ($movementGroups as $group) {
            /** @var Paca $paca */
            $paca = $group['paca'];
            /** @var Warehouse $warehouse */
            $warehouse = $group['warehouse'];

            if (($group['quantityOut'] ?? 0) > 0) {
                $this->recordTransferMovement(
                    paca: $paca,
                    warehouse: $warehouse,
                    bin: null,
                    reason: $transferOutReason,
                    user: $user,
                    quantityIn: 0,
                    quantityOut: (int) $group['quantityOut'],
                    notes: sprintf('Traspaso masivo hacia %s. Motivo: %s', $destinationWarehouse->getName(), $reason->getName()),
                );
            }

            if (($group['quantityIn'] ?? 0) > 0) {
                $this->recordTransferMovement(
                    paca: $paca,
                    warehouse: $warehouse,
                    bin: $warehouse->getId() === $destinationWarehouse->getId() ? $destinationBin : null,
                    reason: $transferInReason,
                    user: $user,
                    quantityIn: (int) $group['quantityIn'],
                    quantityOut: 0,
                    notes: sprintf('Traspaso masivo desde otra bodega. Motivo: %s', $reason->getName()),
                );
            }
        }

        foreach ($affectedPairs as [$paca]) {
            $this->inventoryManager->updateCachedStock($paca);
        }

        return [
            'transferred' => $transferred,
            'skipped' => $skipped + max(0, count($ids) - count($units)),
            'message' => sprintf(
                '%d unidad(es) traspasada(s) a %s.%s',
                $transferred,
                $destinationWarehouse->getName(),
                $skipped > 0 ? sprintf(' %d omitida(s) por no estar disponibles o no requerir cambio.', $skipped) : '',
            ),
        ];
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

        $sourceWarehouse = $u->getWarehouse();
        $sourceBin = $u->getWarehouseBin();

        $sameWarehouse = $sourceWarehouse->getId() === $warehouse->getId();
        $sameBin = ($sourceBin?->getId() ?? null) === ($bin?->getId() ?? null);
        if ($sameWarehouse && $sameBin) {
            return new PacaUnitResponse($u);
        }

        $transferOutReason = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => InventoryReason::CODE_TRANSFER_OUT]);
        $transferInReason = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => InventoryReason::CODE_TRANSFER_IN]);
        if ($transferOutReason === null || $transferInReason === null) {
            throw new BadRequestHttpException('No están configurados los motivos de traspaso de inventario.');
        }

        $u->setWarehouse($warehouse);
        $u->setWarehouseBin($bin);
        $this->em->flush();

        $systemUser = $this->resolveSystemUser();
        if ($systemUser !== null) {
            $this->recordTransferMovement(
                paca: $u->getPaca(),
                warehouse: $sourceWarehouse,
                bin: $sourceBin,
                reason: $transferOutReason,
                user: $systemUser,
                quantityIn: 0,
                quantityOut: 1,
                notes: sprintf('Traspaso individual de unidad %s hacia %s', $u->getSerial(), $warehouse->getName()),
            );
            $this->recordTransferMovement(
                paca: $u->getPaca(),
                warehouse: $warehouse,
                bin: $bin,
                reason: $transferInReason,
                user: $systemUser,
                quantityIn: 1,
                quantityOut: 0,
                notes: sprintf('Recepción por traspaso individual de unidad %s desde %s', $u->getSerial(), $sourceWarehouse->getName()),
            );
        }

        $this->inventoryManager->updateCachedStock($u->getPaca());

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

    /**
     * Mark units as labeled in bulk.
     * @param int[] $ids
     * @return int number of rows updated
     */
    public function markLabeled(array $ids): int
    {
        if (empty($ids)) {
            throw new BadRequestHttpException('Debe proporcionar al menos un ID.');
        }
        return $this->pacaUnitRepo->markLabeledBulk($ids);
    }

    /**
     * Reserve units in bulk (only AVAILABLE units are affected).
     * @param int[] $ids
     * @return array{reserved: int, skipped: int}
     */
    public function reserveBulk(array $ids): array
    {
        if (empty($ids)) {
            throw new BadRequestHttpException('Debe proporcionar al menos un ID.');
        }

        $updated = $this->em->createQuery(
            "UPDATE App\Entity\PacaUnit u
             SET u.status = :reserved
             WHERE u.id IN (:ids) AND u.status = :available"
        )
            ->setParameter('reserved', PacaUnit::STATUS_RESERVED)
            ->setParameter('available', PacaUnit::STATUS_AVAILABLE)
            ->setParameter('ids', $ids)
            ->execute();

        return [
            'reserved' => $updated,
            'skipped'  => \count($ids) - $updated,
        ];
    }

    /**
     * Retire units in bulk. Only AVAILABLE units can be adjusted out.
     * @param array<int, int|string> $identifiers
     * @return array{deleted: int, skipped: int}
     */
    public function deleteBulk(array $identifiers): array
    {
        if (empty($identifiers)) {
            throw new BadRequestHttpException('Debe proporcionar al menos un ID.');
        }

        $numericIds = [];
        $serials = [];

        foreach ($identifiers as $identifier) {
            if (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier))) {
                $value = (int) $identifier;
                if ($value > 0) {
                    $numericIds[] = $value;
                }
                continue;
            }

            if (is_string($identifier) && trim($identifier) !== '') {
                $serials[] = trim($identifier);
            }
        }

        if (empty($numericIds) && empty($serials)) {
            throw new BadRequestHttpException('Los identificadores proporcionados no son válidos.');
        }

        $qb = $this->pacaUnitRepo->createQueryBuilder('u')
            ->leftJoin('u.paca', 'p')->addSelect('p');

        if (!empty($numericIds) && !empty($serials)) {
            $qb->where('u.id IN (:ids) OR u.serial IN (:serials)')
                ->setParameter('ids', array_values(array_unique($numericIds)))
                ->setParameter('serials', array_values(array_unique($serials)));
        } elseif (!empty($numericIds)) {
            $qb->where('u.id IN (:ids)')
                ->setParameter('ids', array_values(array_unique($numericIds)));
        } else {
            $qb->where('u.serial IN (:serials)')
                ->setParameter('serials', array_values(array_unique($serials)));
        }

        $units = $qb->getQuery()->getResult();

        if (empty($units)) {
            throw new NotFoundHttpException('No se encontraron unidades para eliminar.');
        }

        $affectedPacas = [];
        $deleted = 0;
        $skipped = 0;
        $deletableStatuses = [PacaUnit::STATUS_AVAILABLE];
        $lossReason = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => InventoryReason::CODE_LOSS]);
        if ($lossReason === null) {
            throw new BadRequestHttpException('No está configurado el motivo de baja de inventario.');
        }

        $systemUser = $this->resolveSystemUser();
        if ($systemUser === null) {
            throw new BadRequestHttpException('No se encontró un usuario del sistema para registrar el movimiento de inventario.');
        }

        $unitsToAdjust = [];

        foreach ($units as $unit) {
            if (!$unit instanceof PacaUnit) {
                continue;
            }

            if (!in_array($unit->getStatus(), $deletableStatuses, true)) {
                $skipped++;
                continue;
            }

            $paca = $unit->getPaca();
            $affectedPacas[$paca->getId()] = $paca;
            $unitsToAdjust[] = $unit;
        }

        foreach ($unitsToAdjust as $unit) {
            $unit->setStatus(PacaUnit::STATUS_DAMAGED);
            $this->recordTransferMovement(
                paca: $unit->getPaca(),
                warehouse: $unit->getWarehouse(),
                bin: $unit->getWarehouseBin(),
                reason: $lossReason,
                user: $systemUser,
                quantityIn: 0,
                quantityOut: 1,
                notes: sprintf('Baja operativa de unidad %s desde módulo de unidades.', $unit->getSerial()),
            );
            $deleted++;
        }

        $this->em->flush();

        foreach ($affectedPacas as $paca) {
            $this->inventoryManager->updateCachedStock($paca);
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped + max(0, \count($identifiers) - \count($units)),
        ];
    }

    private function resolveSystemUser(): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy([], ['id' => 'ASC']);
    }

    private function recordTransferMovement(
        Paca $paca,
        Warehouse $warehouse,
        ?WarehouseBin $bin,
        InventoryReason $reason,
        User $user,
        int $quantityIn,
        int $quantityOut,
        string $notes,
    ): void {
        $movement = new InventoryMovement();
        $movement->setCompany($warehouse->getCompany());
        $movement->setPaca($paca);
        $movement->setWarehouse($warehouse);
        $movement->setWarehouseBin($bin);
        $movement->setReason($reason);
        $movement->setUser($user);
        $movement->setMovementType($reason->getDirection());
        $movement->setReferenceType('bulk_transfer');
        $movement->setReferenceId(null);
        $movement->setQtyIn($quantityIn);
        $movement->setQtyOut($quantityOut);
        $movement->setBalanceAfter($this->countTrackedUnitsInWarehouse($paca, $warehouse));
        $movement->setUnitCost($paca->getPurchasePrice());
        $movement->setNotes($notes);

        $this->em->persist($movement);
        $this->em->flush();
    }

    private function countTrackedUnitsInWarehouse(Paca $paca, Warehouse $warehouse): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(PacaUnit::class, 'u')
            ->where('u.paca = :paca')
            ->andWhere('u.warehouse = :warehouse')
            ->andWhere('u.status IN (:statuses)')
            ->setParameter('paca', $paca)
            ->setParameter('warehouse', $warehouse)
            ->setParameter('statuses', [
                PacaUnit::STATUS_AVAILABLE,
                PacaUnit::STATUS_RESERVED,
                PacaUnit::STATUS_PICKED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
