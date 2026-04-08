<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateInventoryCountRequest;
use App\DTO\Request\UpdateInventoryCountRequest;
use App\DTO\Response\InventoryCountDetailResponse;
use App\DTO\Response\InventoryCountResponse;
use App\Entity\Company;
use App\Entity\InventoryCount;
use App\Entity\InventoryCountDetail;
use App\Entity\InventoryReason;
use App\Entity\Paca;
use App\Entity\PacaUnit;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\PacaUnitRepository;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventoryCountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class InventoryCountService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InventoryCountRepository $countRepository,
        private PacaUnitRepository $pacaUnitRepo,
        private InventoryManager $inventoryManager,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->countRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            warehouseId: $pagination->warehouseId ?? null,
            status: null,
            companyId: $pagination->companyId ?? null,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (InventoryCount $c) => new InventoryCountResponse($c),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): array
    {
        $count = $this->countRepository->find($id);
        if ($count === null) {
            throw new NotFoundHttpException(sprintf('Conteo de inventario con ID %d no encontrado.', $id));
        }

        $details = array_map(
            static fn (InventoryCountDetail $d) => new InventoryCountDetailResponse($d),
            $count->getDetails()->toArray(),
        );

        return [
            'count' => new InventoryCountResponse($count),
            'details' => $details,
        ];
    }

    public function create(CreateInventoryCountRequest $request, User $user): InventoryCountResponse
    {
        $company = $this->em->getRepository(Company::class)->find($request->companyId);
        if ($company === null) {
            throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $request->companyId));
        }

        $warehouse = $this->em->getRepository(Warehouse::class)->find($request->warehouseId);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Bodega con ID %d no encontrada.', $request->warehouseId));
        }

        $folio = sprintf('CNT-%s-%04d', date('Ymd'), random_int(1, 9999));

        $count = new InventoryCount();
        $count->setCompany($company);
        $count->setWarehouse($warehouse);
        $count->setUser($user);
        $count->setFolio($folio);
        $count->setCountDate(new \DateTime($request->countDate));
        $count->setNotes($request->notes);

        $this->em->persist($count);
        $this->em->flush();

        return new InventoryCountResponse($count);
    }

    public function startCount(int $id): array
    {
        $count = $this->countRepository->find($id);
        if ($count === null) {
            throw new NotFoundHttpException(sprintf('Conteo de inventario con ID %d no encontrado.', $id));
        }

        if ($count->getStatus() !== 'DRAFT') {
            throw new ConflictHttpException('Solo se pueden iniciar conteos en estado DRAFT.');
        }

        // Find all active pacas in the warehouse (via PacaUnit)
        $pacas = $this->em->getRepository(Paca::class)
            ->createQueryBuilder('p')
            ->innerJoin('App\Entity\PacaUnit', 'pu', 'WITH', 'pu.paca = p')
            ->where('pu.warehouse = :warehouse')
            ->andWhere('pu.status IN (:statuses)')
            ->andWhere('p.active = true')
            ->setParameter('warehouse', $count->getWarehouse())
            ->setParameter('statuses', ['AVAILABLE', 'RESERVED'])
            ->groupBy('p.id')
            ->getQuery()
            ->getResult();

        foreach ($pacas as $paca) {
            $detail = new InventoryCountDetail();
            $detail->setPaca($paca);
            $detail->setSystemQty(
                $this->pacaUnitRepo->countByPacaAndWarehouseForCount($paca->getId(), $count->getWarehouse()->getId())
            );
            $count->addDetail($detail);
        }

        $count->setStatus('IN_PROGRESS');
        $count->setTotalItems(count($pacas));

        $this->em->flush();

        $details = array_map(
            static fn (InventoryCountDetail $d) => new InventoryCountDetailResponse($d),
            $count->getDetails()->toArray(),
        );

        return [
            'count' => new InventoryCountResponse($count),
            'details' => $details,
        ];
    }

    public function updateDetail(int $countId, int $detailId, ?int $countedQty, ?string $notes): InventoryCountDetailResponse
    {
        $count = $this->countRepository->find($countId);
        if ($count === null) {
            throw new NotFoundHttpException(sprintf('Conteo de inventario con ID %d no encontrado.', $countId));
        }

        if ($count->getStatus() !== 'IN_PROGRESS' && $count->getStatus() !== 'RECOUNT') {
            throw new ConflictHttpException('Solo se pueden actualizar detalles de conteos EN PROGRESO o en RECONTEO.');
        }

        $detail = null;
        foreach ($count->getDetails() as $d) {
            if ($d->getId() === $detailId) {
                $detail = $d;
                break;
            }
        }

        if ($detail === null) {
            throw new NotFoundHttpException(sprintf('Detalle de conteo con ID %d no encontrado.', $detailId));
        }

        if ($countedQty !== null) {
            $detail->setCountedQty($countedQty);
            $detail->setDifference($countedQty - $detail->getSystemQty());
            $detail->setStatus($count->getStatus() === 'RECOUNT' ? 'RECOUNTED' : 'COUNTED');
            $detail->setCountedAt(new \DateTimeImmutable());
        }

        if ($notes !== null) {
            $detail->setNotes($notes);
        }

        $this->em->flush();

        return new InventoryCountDetailResponse($detail);
    }

    public function finalizeCount(int $countId, User $user): array
    {
        $count = $this->countRepository->find($countId);
        if ($count === null) {
            throw new NotFoundHttpException(sprintf('Conteo de inventario con ID %d no encontrado.', $countId));
        }

        if ($count->getStatus() !== 'IN_PROGRESS') {
            throw new ConflictHttpException('Solo se pueden finalizar conteos EN PROGRESO.');
        }

        // Validate all details have been counted
        foreach ($count->getDetails() as $detail) {
            if ($detail->getCountedQty() === null) {
                throw new BadRequestHttpException(
                    sprintf('El detalle para la paca "%s" no ha sido contado.', $detail->getPaca()->getCode()),
                );
            }
        }

        // Find adjustment reasons
        $adjustmentIn = $this->em->getRepository(InventoryReason::class)->findOneBy([
            'code' => 'ADJUSTMENT_IN',
        ]);
        $adjustmentOut = $this->em->getRepository(InventoryReason::class)->findOneBy([
            'code' => 'ADJUSTMENT_OUT',
        ]);

        $discrepancies = 0;

        foreach ($count->getDetails() as $detail) {
            $diff = $detail->getDifference();
            if ($diff !== null && $diff !== 0) {
                $discrepancies++;
                $detail->setStatus('ADJUSTED');

                $paca = $detail->getPaca();
                $warehouse = $count->getWarehouse();

                if ($diff > 0 && $adjustmentIn !== null) {
                    // Surplus: create new PacaUnits
                    $existingCount = $this->em->getRepository(PacaUnit::class)->count(['paca' => $paca]);
                    for ($i = 0; $i < abs($diff); $i++) {
                        $unit = new PacaUnit();
                        $unit->setSerial(sprintf('%s-%04d', $paca->getCode(), $existingCount + $i + 1));
                        $unit->setPaca($paca);
                        $unit->setWarehouse($warehouse);
                        $this->em->persist($unit);
                    }
                    $this->inventoryManager->recordMovement(
                        paca: $paca,
                        warehouse: $warehouse,
                        bin: null,
                        reason: $adjustmentIn,
                        user: $user,
                        quantity: abs($diff),
                        referenceType: 'INVENTORY_COUNT',
                        referenceId: $count->getId(),
                        notes: sprintf('Ajuste por conteo físico %s', $count->getFolio()),
                        forceAdjustment: true,
                    );
                } elseif ($diff < 0 && $adjustmentOut !== null) {
                    // Shortage: mark PacaUnits as DAMAGED
                    $unitsToMark = $this->pacaUnitRepo->findAvailableInWarehouse(
                        $paca->getId(),
                        $warehouse->getId(),
                        abs($diff),
                    );
                    foreach ($unitsToMark as $unit) {
                        $unit->setStatus(PacaUnit::STATUS_DAMAGED);
                    }
                    $this->inventoryManager->recordMovement(
                        paca: $paca,
                        warehouse: $warehouse,
                        bin: null,
                        reason: $adjustmentOut,
                        user: $user,
                        quantity: abs($diff),
                        referenceType: 'INVENTORY_COUNT',
                        referenceId: $count->getId(),
                        notes: sprintf('Ajuste por conteo físico %s', $count->getFolio()),
                        forceAdjustment: true,
                    );
                }
            }
        }

        $count->setDiscrepancies($discrepancies);
        $count->setStatus('COMPLETED');

        $this->em->flush();

        $details = array_map(
            static fn (InventoryCountDetail $d) => new InventoryCountDetailResponse($d),
            $count->getDetails()->toArray(),
        );

        return [
            'count' => new InventoryCountResponse($count),
            'details' => $details,
        ];
    }

    public function startRecount(int $id): array
    {
        $count = $this->countRepository->find($id);
        if ($count === null) {
            throw new NotFoundHttpException(sprintf('Conteo de inventario con ID %d no encontrado.', $id));
        }

        if ($count->getStatus() !== 'COMPLETED') {
            throw new ConflictHttpException('Solo se pueden recontar conteos en estado COMPLETADO.');
        }

        if ($count->getDiscrepancies() === 0) {
            throw new BadRequestHttpException('No hay discrepancias para recontar.');
        }

        // Preserve first count values for adjusted items
        foreach ($count->getDetails() as $detail) {
            if ($detail->getStatus() === 'ADJUSTED') {
                $detail->setFirstCountedQty($detail->getCountedQty());
                $detail->setFirstDifference($detail->getDifference());
                $detail->setFirstCountedAt($detail->getCountedAt());
            }
        }

        $count->setStatus('RECOUNT');
        $this->em->flush();

        $details = array_map(
            static fn (InventoryCountDetail $d) => new InventoryCountDetailResponse($d),
            $count->getDetails()->toArray(),
        );

        return [
            'count' => new InventoryCountResponse($count),
            'details' => $details,
        ];
    }

    public function finalizeRecount(int $countId, User $user): array
    {
        $count = $this->countRepository->find($countId);
        if ($count === null) {
            throw new NotFoundHttpException(sprintf('Conteo de inventario con ID %d no encontrado.', $countId));
        }

        if ($count->getStatus() !== 'RECOUNT') {
            throw new ConflictHttpException('Solo se pueden finalizar reconteos en estado RECOUNT.');
        }

        $adjustmentIn = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => 'ADJUSTMENT_IN']);
        $adjustmentOut = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => 'ADJUSTMENT_OUT']);

        $warnings = [];

        foreach ($count->getDetails() as $detail) {
            if ($detail->getStatus() !== 'RECOUNTED') {
                continue;
            }

            $firstCounted = $detail->getFirstCountedQty();
            $recounted = $detail->getCountedQty();

            if ($firstCounted === null || $recounted === null) {
                continue;
            }

            $correction = $recounted - $firstCounted;

            if ($correction !== 0) {
                $paca = $detail->getPaca();
                $warehouse = $count->getWarehouse();
                $stockBefore = $paca->getStock();

                if ($correction > 0 && $adjustmentIn !== null) {
                    // Surplus: create new PacaUnits
                    $existingCount = $this->em->getRepository(PacaUnit::class)->count(['paca' => $paca]);
                    for ($i = 0; $i < abs($correction); $i++) {
                        $unit = new PacaUnit();
                        $unit->setSerial(sprintf('%s-%04d', $paca->getCode(), $existingCount + $i + 1));
                        $unit->setPaca($paca);
                        $unit->setWarehouse($warehouse);
                        $this->em->persist($unit);
                    }
                    $this->inventoryManager->recordMovement(
                        paca: $paca,
                        warehouse: $warehouse,
                        bin: null,
                        reason: $adjustmentIn,
                        user: $user,
                        quantity: abs($correction),
                        referenceType: 'INVENTORY_COUNT',
                        referenceId: $count->getId(),
                        notes: sprintf('Corrección por reconteo %s (1er: %d, 2do: %d)', $count->getFolio(), $firstCounted, $recounted),
                        forceAdjustment: true,
                    );
                } elseif ($correction < 0 && $adjustmentOut !== null) {
                    $requiredOut = abs($correction);
                    // Shortage: mark PacaUnits as DAMAGED
                    $unitsToMark = $this->pacaUnitRepo->findAvailableInWarehouse(
                        $paca->getId(),
                        $warehouse->getId(),
                        $requiredOut,
                    );
                    foreach ($unitsToMark as $unit) {
                        $unit->setStatus(PacaUnit::STATUS_DAMAGED);
                    }
                    if (count($unitsToMark) < $requiredOut) {
                        $warnings[] = sprintf(
                            '%s: se requería ajustar -%d pero solo había %d disponibles (se ajustaron %d)',
                            $paca->getCode(),
                            $requiredOut,
                            count($unitsToMark),
                            count($unitsToMark),
                        );
                    }
                    $this->inventoryManager->recordMovement(
                        paca: $paca,
                        warehouse: $warehouse,
                        bin: null,
                        reason: $adjustmentOut,
                        user: $user,
                        quantity: $requiredOut,
                        referenceType: 'INVENTORY_COUNT',
                        referenceId: $count->getId(),
                        notes: sprintf('Corrección por reconteo %s (1er: %d, 2do: %d)', $count->getFolio(), $firstCounted, $recounted),
                        forceAdjustment: true,
                    );
                }
            }
        }

        // Count remaining discrepancies: items that still have difference != 0
        $discrepancies = 0;
        foreach ($count->getDetails() as $detail) {
            if (in_array($detail->getStatus(), ['ADJUSTED', 'RECOUNTED'], true)) {
                $diff = $detail->getDifference();
                if ($diff !== null && $diff !== 0) {
                    $discrepancies++;
                }
            }
        }

        $count->setDiscrepancies($discrepancies);
        $count->setStatus('COMPLETED');
        $this->em->flush();

        $details = array_map(
            static fn (InventoryCountDetail $d) => new InventoryCountDetailResponse($d),
            $count->getDetails()->toArray(),
        );

        $result = [
            'count' => new InventoryCountResponse($count),
            'details' => $details,
        ];

        if (!empty($warnings)) {
            $result['warnings'] = $warnings;
        }

        return $result;
    }

    public function delete(int $id): void
    {
        $count = $this->countRepository->find($id);
        if ($count === null) {
            throw new NotFoundHttpException(sprintf('Conteo de inventario con ID %d no encontrado.', $id));
        }

        if ($count->getStatus() !== 'DRAFT') {
            throw new ConflictHttpException('Solo se pueden eliminar conteos en estado DRAFT.');
        }

        $this->em->remove($count);
        $this->em->flush();
    }
}
