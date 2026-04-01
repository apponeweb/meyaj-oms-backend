<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Response\InventorySnapshotResponse;
use App\Entity\InventorySnapshot;
use App\Entity\Paca;
use App\Entity\Warehouse;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventorySnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InventorySnapshotService
{
    private const LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        private EntityManagerInterface $em,
        private InventorySnapshotRepository $snapshotRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->snapshotRepository->createPaginatedQueryBuilder(
            companyId: $pagination->companyId,
            warehouseId: $pagination->warehouseId,
            dateFrom: $pagination->dateFrom,
            dateTo: $pagination->dateTo,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (InventorySnapshot $s) => new InventorySnapshotResponse($s),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function generateSnapshot(\DateTimeImmutable $date = null): int
    {
        $date ??= new \DateTimeImmutable();
        $warehouses = $this->em->getRepository(Warehouse::class)->findBy(['isActive' => true]);
        $count = 0;

        foreach ($warehouses as $warehouse) {
            $existing = $this->snapshotRepository->findOneBy([
                'warehouse' => $warehouse,
                'company' => $warehouse->getCompany(),
                'snapshotDate' => $date,
            ]);

            if ($existing !== null) {
                continue;
            }

            $pacas = $this->em->getRepository(Paca::class)->findBy([
                'warehouse' => $warehouse,
                'active' => true,
            ]);

            $totalPacas = count($pacas);
            $totalStock = 0;
            $totalValuePurchase = '0.00';
            $totalValueSelling = '0.00';
            $lowStockCount = 0;
            $outOfStockCount = 0;

            foreach ($pacas as $paca) {
                $stock = $paca->getStock();
                $totalStock += $stock;
                $totalValuePurchase = bcadd($totalValuePurchase, bcmul($paca->getPurchasePrice(), (string) $stock, 2), 2);
                $totalValueSelling = bcadd($totalValueSelling, bcmul($paca->getSellingPrice(), (string) $stock, 2), 2);

                if ($stock === 0) {
                    $outOfStockCount++;
                } elseif ($stock <= self::LOW_STOCK_THRESHOLD) {
                    $lowStockCount++;
                }
            }

            $snapshot = new InventorySnapshot();
            $snapshot->setCompany($warehouse->getCompany());
            $snapshot->setWarehouse($warehouse);
            $snapshot->setSnapshotDate($date);
            $snapshot->setTotalPacas($totalPacas);
            $snapshot->setTotalStock($totalStock);
            $snapshot->setTotalValuePurchase($totalValuePurchase);
            $snapshot->setTotalValueSelling($totalValueSelling);
            $snapshot->setLowStockCount($lowStockCount);
            $snapshot->setOutOfStockCount($outOfStockCount);

            $this->em->persist($snapshot);
            $count++;
        }

        $this->em->flush();

        return $count;
    }
}
