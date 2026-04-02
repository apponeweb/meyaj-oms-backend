<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreatePacaRequest;
use App\DTO\Request\UpdatePacaRequest;
use App\DTO\Response\PacaResponse;
use App\Entity\Paca;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventoryReservationRepository;
use App\Repository\PacaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PacaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PacaRepository $pacaRepository,
        private InventoryReservationRepository $reservationRepository,
        private Paginator $paginator,
    ) {}

    public function list(
        PaginationRequest $pagination, 
        ?int $brandId = null, 
        ?int $supplierId = null, 
        ?bool $active = null,
        ?int $companyId = null,
        ?int $warehouseId = null,
        ?int $warehouseBinId = null
    ): PaginatedResponse {
        $qb = $this->pacaRepository->createPaginatedQueryBuilder(
            $pagination->search, 
            $brandId, 
            $supplierId, 
            $active,
            $companyId,
            $warehouseId,
            $warehouseBinId
        );
        $result = $this->paginator->paginate($qb, $pagination);

        $pacaIds = array_map(static fn (Paca $p) => $p->getId(), $result->data);
        $reservedMap = $this->reservationRepository->getActiveReservedQuantityByPacaIds($pacaIds);

        return new PaginatedResponse(
            data: array_map(static fn (Paca $p) => new PacaResponse(
                $p,
                $p->getStock() - ($reservedMap[$p->getId()] ?? 0),
            ), $result->data),
            meta: $result->meta,
        );
    }

    public function show(int $id): PacaResponse
    {
        $p = $this->pacaRepository->find($id);
        if ($p === null) throw new NotFoundHttpException(\sprintf('Paca con ID %d no encontrada.', $id));
        $reserved = $this->reservationRepository->getActiveReservedQuantity($p->getId());
        return new PacaResponse($p, $p->getStock() - $reserved);
    }

    public function create(CreatePacaRequest $r): PacaResponse
    {
        $p = new Paca();
        $p->setCode($r->code);
        $p->setName($r->name);
        $p->setDescription($r->description);
        $p->setPurchasePrice($r->purchasePrice);
        $p->setSellingPrice($r->sellingPrice);
        $p->setStock($r->stock);
        $p->setPieceCount($r->pieceCount);
        $p->setWeight($r->weight);
        $this->setRelations($p, $r->brandId, $r->labelId, $r->qualityGradeId, $r->seasonId, $r->genderId, $r->garmentTypeId, $r->fabricTypeId, $r->sizeProfileId, $r->supplierId);
        $this->syncLocations($p, $r->locations ?? []);
        $this->em->persist($p);
        $this->em->flush();
        return new PacaResponse($p);
    }

    public function update(int $id, UpdatePacaRequest $r): PacaResponse
    {
        $p = $this->pacaRepository->find($id);
        if ($p === null) throw new NotFoundHttpException(sprintf('Paca con ID %d no encontrada.', $id));
        if ($r->code !== null) $p->setCode($r->code);
        if ($r->name !== null) $p->setName($r->name);
        if ($r->description !== null) $p->setDescription($r->description);
        if ($r->purchasePrice !== null) $p->setPurchasePrice($r->purchasePrice);
        if ($r->sellingPrice !== null) $p->setSellingPrice($r->sellingPrice);
        if ($r->stock !== null) $p->setStock($r->stock);
        if ($r->pieceCount !== null) $p->setPieceCount($r->pieceCount);
        if ($r->weight !== null) $p->setWeight($r->weight);
        if ($r->active !== null) $p->setActive($r->active);
        $this->setRelations($p, $r->brandId, $r->labelId, $r->qualityGradeId, $r->seasonId, $r->genderId, $r->garmentTypeId, $r->fabricTypeId, $r->sizeProfileId, $r->supplierId);
        if ($r->locations !== null) {
            $this->syncLocations($p, $r->locations);
        }
        $this->em->flush();
        return new PacaResponse($p);
    }

    public function delete(int $id): void
    {
        $p = $this->pacaRepository->find($id);
        if ($p === null) throw new NotFoundHttpException(sprintf('Paca con ID %d no encontrada.', $id));
        $this->em->remove($p);
        $this->em->flush();
    }

    private function setRelations(Paca $p, ?int $brandId, ?int $labelId, ?int $qualityGradeId, ?int $seasonId, ?int $genderId, ?int $garmentTypeId, ?int $fabricTypeId, ?int $sizeProfileId, ?int $supplierId): void
    {
        if ($brandId !== null) $p->setBrand($this->em->getRepository(\App\Entity\Brand::class)->find($brandId));
        if ($labelId !== null) $p->setLabel($this->em->getRepository(\App\Entity\LabelCatalog::class)->find($labelId));
        if ($qualityGradeId !== null) $p->setQualityGrade($this->em->getRepository(\App\Entity\QualityGrade::class)->find($qualityGradeId));
        if ($seasonId !== null) $p->setSeason($this->em->getRepository(\App\Entity\SeasonCatalog::class)->find($seasonId));
        if ($genderId !== null) $p->setGender($this->em->getRepository(\App\Entity\GenderCatalog::class)->find($genderId));
        if ($garmentTypeId !== null) $p->setGarmentType($this->em->getRepository(\App\Entity\GarmentType::class)->find($garmentTypeId));
        if ($fabricTypeId !== null) $p->setFabricType($this->em->getRepository(\App\Entity\FabricType::class)->find($fabricTypeId));
        if ($sizeProfileId !== null) $p->setSizeProfile($this->em->getRepository(\App\Entity\SizeProfile::class)->find($sizeProfileId));
        if ($supplierId !== null) $p->setSupplier($this->em->getRepository(\App\Entity\Supplier::class)->find($supplierId));
    }

    private function syncLocations(Paca $p, array $locations): void
    {
        $p->clearLocations();
        $this->em->flush();

        foreach ($locations as $loc) {
            $warehouse = $this->em->getRepository(\App\Entity\Warehouse::class)->find($loc['warehouseId']);
            if ($warehouse === null) continue;

            $location = new \App\Entity\PacaLocation();
            $location->setWarehouse($warehouse);

            if (!empty($loc['warehouseBinId'])) {
                $bin = $this->em->getRepository(\App\Entity\WarehouseBin::class)->find($loc['warehouseBinId']);
                $location->setWarehouseBin($bin);
            }

            $p->addLocation($location);
        }
    }
}
