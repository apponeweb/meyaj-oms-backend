<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InventoryMovement;
use App\Entity\InventoryReason;
use App\Entity\Paca;
use App\Entity\PacaImportLog;
use App\Entity\PacaUnit;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\PacaImportLogRepository;
use App\Repository\PacaRepository;
use App\Repository\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PacaExcelService
{
    private const HEADERS = [
        'Código', 'Nombre', 'Descripción',
        'Precio Compra', 'Precio Venta', 'Stock', 'Piezas', 'Peso (kg)',
        'Proveedor', 'Marca', 'Etiqueta', 'Calidad', 'Temporada',
        'Género', 'Tipo Prenda', 'Tipo Tela', 'Perfil Talla', 'Activo',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private PacaRepository $pacaRepository,
        private PacaImportLogRepository $importLogRepository,
        private WarehouseRepository $warehouseRepository,
        private InventoryManager $inventoryManager,
        private string $importDir,
    ) {}

    // ── Export ────────────────────────────────────────────────────────

    public function export(): StreamedResponse
    {
        $pacas = $this->pacaRepository->createPaginatedQueryBuilder()
            ->orderBy('p.code', 'ASC')
            ->getQuery()
            ->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pacas');

        foreach (self::HEADERS as $col => $header) {
            $letter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$letter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(\count(self::HEADERS));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '10B981']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        /** @var Paca $paca */
        foreach ($pacas as $paca) {
            $values = [
                $paca->getCode(), $paca->getName(), $paca->getDescription() ?? '',
                (float) $paca->getPurchasePrice(), (float) $paca->getSellingPrice(),
                $paca->getStock(), $paca->getPieceCount(),
                $paca->getWeight() !== null ? (float) $paca->getWeight() : null,
                $paca->getSupplier()?->getName() ?? '', $paca->getBrand()?->getName() ?? '',
                $paca->getLabel()?->getName() ?? '', $paca->getQualityGrade()?->getName() ?? '',
                $paca->getSeason()?->getName() ?? '', $paca->getGender()?->getName() ?? '',
                $paca->getGarmentType()?->getName() ?? '', $paca->getFabricType()?->getName() ?? '',
                $paca->getSizeProfile()?->getName() ?? '', $paca->isActive() ? 'Sí' : 'No',
            ];
            foreach ($values as $col => $value) {
                $letter = Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue("{$letter}{$row}", $value);
            }
            $row++;
        }

        foreach (range(1, \count(self::HEADERS)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });

        $filename = 'pacas_' . date('Y-m-d_His') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    // ── Import — Phase 1: upload & create log ────────────────────────

    public function uploadImportFile(\SplFileInfo $file, string $originalName, User $user): PacaImportLog
    {
        if (!is_dir($this->importDir)) {
            mkdir($this->importDir, 0755, true);
        }

        $storedName = uniqid('import_') . '.xlsx';
        copy($file->getPathname(), $this->importDir . '/' . $storedName);

        // Count data rows (exclude header)
        $spreadsheet = IOFactory::load($this->importDir . '/' . $storedName);
        $totalRows = max(0, $spreadsheet->getActiveSheet()->getHighestDataRow() - 1);

        $log = new PacaImportLog();
        $log->setFilename($storedName);
        $log->setOriginalFilename($originalName);
        $log->setTotalRows($totalRows);
        $log->setUser($user);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    // ── Import — Phase 2: process (synchronous, saves progress per row) ──

    public function processImport(int $importId, int $warehouseId, bool $replaceUnits = false): array
    {
        $log = $this->importLogRepository->find($importId);
        if ($log === null) {
            throw new NotFoundHttpException('Import log not found.');
        }
        if ($log->getStatus() !== PacaImportLog::STATUS_PENDING) {
            throw new BadRequestHttpException('Esta importación ya fue procesada.');
        }

        $warehouse = $this->warehouseRepository->find($warehouseId);
        if ($warehouse === null) {
            throw new BadRequestHttpException('La bodega especificada no existe.');
        }

        $filePath = $this->importDir . '/' . $log->getFilename();
        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Archivo de importación no encontrado.');
        }

        $log->setStatus(PacaImportLog::STATUS_PROCESSING);
        $this->em->flush();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (\count($rows) < 2) {
                throw new \RuntimeException('El archivo no contiene filas de productos para procesar.');
            }

            $headerRow = $rows[1] ?? [];
            unset($rows[1]);

            $colMap = [];
            foreach ($headerRow as $letter => $value) {
                $colMap[mb_strtolower(trim((string) $value))] = $letter;
            }

            $catalogMap = $this->buildCatalogLookups();

            // Build existing names set for uniqueness validation
            $existingNames = [];
            foreach ($this->pacaRepository->findAll() as $existing) {
                $existingNames[mb_strtolower($existing->getName())] = $existing->getCode();
            }
            $namesInFile = [];

            $created = 0;
            $updated = 0;
            $errors = [];
            $processed = 0;
            $adjustmentInReason = $this->requireInventoryReason(InventoryReason::CODE_ADJ_IN);
            $adjustmentOutReason = $this->requireInventoryReason(InventoryReason::CODE_ADJ_OUT);

            foreach ($rows as $rowNum => $row) {
                try {
                    $code = trim((string) ($row[$colMap['código'] ?? $colMap['codigo'] ?? ''] ?? ''));
                    if ($code === '') {
                        $processed++;
                        $log->setProcessedRows($processed);
                        $this->em->flush();
                        continue;
                    }

                    $name = trim((string) ($row[$colMap['nombre'] ?? ''] ?? ''));
                    if ($name === '') {
                        $errors[] = "Fila {$rowNum}: El nombre es obligatorio para el código '{$code}'.";
                        $processed++;
                        $log->setProcessedRows($processed);
                        $log->setErrors($errors);
                        $log->setErrorCount(\count($errors));
                        $this->em->flush();
                        continue;
                    }

                    $nameLower = mb_strtolower($name);
                    if (isset($namesInFile[$nameLower]) && $namesInFile[$nameLower] !== $code) {
                        $errors[] = "Fila {$rowNum}: El nombre '{$name}' está duplicado en el archivo (ya en código '{$namesInFile[$nameLower]}').";
                        $processed++;
                        $log->setProcessedRows($processed);
                        $log->setErrors($errors);
                        $log->setErrorCount(\count($errors));
                        $this->em->flush();
                        continue;
                    }

                    if (isset($existingNames[$nameLower]) && $existingNames[$nameLower] !== $code) {
                        $errors[] = "Fila {$rowNum}: El nombre '{$name}' ya existe en la BD (código '{$existingNames[$nameLower]}').";
                        $processed++;
                        $log->setProcessedRows($processed);
                        $log->setErrors($errors);
                        $log->setErrorCount(\count($errors));
                        $this->em->flush();
                        continue;
                    }

                    $namesInFile[$nameLower] = $code;

                    $existingPaca = $this->pacaRepository->findOneBy(['code' => $code]);
                    $isNew = $existingPaca === null;
                    $paca = $existingPaca ?? new Paca();
                    $previousTotalStock = $isNew ? 0 : $this->countTrackedUnits($paca);
                    $previousWarehouseStock = $isNew ? 0 : $this->countTrackedUnitsInWarehouse($paca, $warehouse);

                    $paca->setCode($code);
                    $paca->setName($name);
                    $this->applyCellValues($paca, $row, $colMap, $catalogMap);

                    if ($isNew) {
                        $this->em->persist($paca);
                        $created++;
                        $existingNames[$nameLower] = $code;
                    } else {
                        $updated++;
                    }

                    $stockDelta = $this->syncUnitsForImport($paca, $warehouse, $isNew, $replaceUnits);
                    $this->em->flush();
                    $this->inventoryManager->updateCachedStock($paca);
                    $currentTotalStock = $paca->getCachedStock();
                    $currentWarehouseStock = $this->countTrackedUnitsInWarehouse($paca, $warehouse);

                    if ($stockDelta > 0) {
                        $this->recordImportMovement(
                            paca: $paca,
                            warehouse: $warehouse,
                            reason: $adjustmentInReason,
                            user: $log->getUser(),
                            quantity: $stockDelta,
                            balanceAfter: $currentWarehouseStock,
                            importFilename: $log->getOriginalFilename(),
                        );
                    } elseif ($stockDelta < 0) {
                        $this->recordImportMovement(
                            paca: $paca,
                            warehouse: $warehouse,
                            reason: $adjustmentOutReason,
                            user: $log->getUser(),
                            quantity: abs($stockDelta),
                            balanceAfter: $currentWarehouseStock,
                            importFilename: $log->getOriginalFilename(),
                        );
                    } elseif ($isNew && $currentTotalStock > 0) {
                        $fallbackReason = $adjustmentInReason;
                        $this->recordImportMovement(
                            paca: $paca,
                            warehouse: $warehouse,
                            reason: $fallbackReason,
                            user: $log->getUser(),
                            quantity: $currentTotalStock,
                            balanceAfter: $currentWarehouseStock,
                            importFilename: $log->getOriginalFilename(),
                        );
                    }

                    if (!$isNew && $previousWarehouseStock !== $currentWarehouseStock && $stockDelta === 0) {
                        $notes = sprintf(
                            'Carga masiva desde archivo %s. El saldo de la bodega %s se recalculó de %d a %d unidades.',
                            $log->getOriginalFilename(),
                            $warehouse->getName(),
                            $previousWarehouseStock,
                            $currentWarehouseStock,
                        );
                        $log->addError("Fila {$rowNum} (código '{$code}'): {$notes}");
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Fila {$rowNum}" . (isset($code) && $code !== '' ? " (código '{$code}')" : '') . ': ' . $this->normalizeImportErrorMessage($e);

                    if (!$this->em->isOpen()) {
                        throw $e;
                    }
                }

                $processed++;
                $log->setProcessedRows($processed);
                $log->setCreatedCount($created);
                $log->setUpdatedCount($updated);
                $log->setErrors($errors);
                $log->setErrorCount(\count($errors));
                $this->em->flush();
            }

            $log->setStatus(PacaImportLog::STATUS_COMPLETED);
            $log->setCompletedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable $e) {
            if ($this->em->isOpen()) {
                $log->setStatus(PacaImportLog::STATUS_FAILED);
                $log->addError($this->normalizeImportErrorMessage($e));
                $log->setCompletedAt(new \DateTimeImmutable());
                $this->em->flush();
            }

            throw $e;
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        return $this->formatLogResponse($log);
    }

    private function syncUnitsForImport(Paca $paca, Warehouse $warehouse, bool $isNew, bool $replaceUnits = false): int
    {
        $targetStock = $paca->getCachedStock();
        $currentAvailableUnits = $this->countAvailableUnitsInWarehouse($paca, $warehouse);
        $delta = $targetStock - $currentAvailableUnits;

        if ($isNew) {
            if ($targetStock <= 0) {
                return 0;
            }
            $this->createUnitsForImport($paca, $warehouse, $targetStock, 0);
            return $targetStock;
        }

        if ($replaceUnits) {
            $this->em->createQuery(
                "DELETE FROM App\Entity\PacaUnit u WHERE u.paca = :paca AND u.warehouse = :warehouse AND u.status = 'AVAILABLE'"
            )
                ->setParameter('paca', $paca)
                ->setParameter('warehouse', $warehouse)
                ->execute();

            if ($targetStock > 0) {
                $this->createUnitsForImport($paca, $warehouse, $targetStock, $this->getHighestSerialOffset($paca));
            }
            return $targetStock - $currentAvailableUnits;
        }

        if ($delta > 0) {
            $this->createUnitsForImport($paca, $warehouse, $delta, $this->getHighestSerialOffset($paca));
            return $delta;
        }

        if ($delta < 0) {
            $this->removeAvailableUnitsForImport($paca, $warehouse, abs($delta));
            return $delta;
        }

        return 0;
    }

    private function createUnitsForImport(Paca $paca, Warehouse $warehouse, int $quantity, int $serialOffset): void
    {
        for ($i = 0; $i < $quantity; $i++) {
            $unit = new PacaUnit();
            $unit->setPaca($paca);
            $unit->setWarehouse($warehouse);
            $unit->setSerial(sprintf('%s-%04d', $paca->getCode(), $serialOffset + $i + 1));
            $unit->setStatus(PacaUnit::STATUS_AVAILABLE);
            $this->em->persist($unit);
        }
    }

    private function removeAvailableUnitsForImport(Paca $paca, Warehouse $warehouse, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $units = $this->em->createQueryBuilder()
            ->select('u')
            ->from(PacaUnit::class, 'u')
            ->where('u.paca = :paca')
            ->andWhere('u.warehouse = :warehouse')
            ->andWhere('u.status = :status')
            ->orderBy('u.id', 'DESC')
            ->setParameter('paca', $paca)
            ->setParameter('warehouse', $warehouse)
            ->setParameter('status', PacaUnit::STATUS_AVAILABLE)
            ->setMaxResults($quantity)
            ->getQuery()
            ->getResult();

        foreach ($units as $unit) {
            $this->em->remove($unit);
        }
    }

    private function countAvailableUnitsInWarehouse(Paca $paca, Warehouse $warehouse): int
    {
        return (int) $this->em->createQuery(
            "SELECT COUNT(u.id) FROM App\Entity\PacaUnit u WHERE u.paca = :paca AND u.warehouse = :warehouse AND u.status = 'AVAILABLE'"
        )
            ->setParameter('paca', $paca)
            ->setParameter('warehouse', $warehouse)
            ->getSingleScalarResult();
    }

    private function countTrackedUnits(Paca $paca): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(PacaUnit::class, 'u')
            ->where('u.paca = :paca')
            ->andWhere('u.status IN (:statuses)')
            ->setParameter('paca', $paca)
            ->setParameter('statuses', [
                PacaUnit::STATUS_AVAILABLE,
                PacaUnit::STATUS_RESERVED,
                PacaUnit::STATUS_PICKED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
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

    private function recordImportMovement(
        Paca $paca,
        Warehouse $warehouse,
        ?InventoryReason $reason,
        User $user,
        int $quantity,
        int $balanceAfter,
        string $importFilename,
    ): void {
        if ($reason === null || $quantity <= 0) {
            return;
        }

        $movement = new InventoryMovement();
        $movement->setCompany($warehouse->getCompany());
        $movement->setPaca($paca);
        $movement->setWarehouse($warehouse);
        $movement->setWarehouseBin(null);
        $movement->setReason($reason);
        $movement->setUser($user);
        $movement->setMovementType($reason->getDirection());
        $movement->setReferenceType('paca_import');
        $movement->setReferenceId($paca->getId());
        $movement->setQtyIn($reason->getDirection() === 'IN' ? $quantity : 0);
        $movement->setQtyOut($reason->getDirection() === 'OUT' ? $quantity : 0);
        $movement->setBalanceAfter($balanceAfter);
        $movement->setUnitCost($paca->getPurchasePrice());
        $movement->setNotes(sprintf('Carga masiva desde archivo %s. Stock objetivo aplicado a la bodega seleccionada; el saldo mostrado corresponde a esa bodega.', $importFilename));

        $this->em->persist($movement);
    }

    private function getHighestSerialOffset(Paca $paca): int
    {
        $serials = $this->em->createQuery(
            'SELECT u.serial FROM App\Entity\PacaUnit u WHERE u.paca = :paca'
        )
            ->setParameter('paca', $paca)
            ->getSingleColumnResult();

        $max = 0;
        foreach ($serials as $serial) {
            if (!is_string($serial)) {
                continue;
            }

            $suffix = strrchr($serial, '-');
            if ($suffix === false) {
                continue;
            }

            $number = (int) ltrim($suffix, '-');
            if ($number > $max) {
                $max = $number;
            }
        }

        return $max;
    }

    // ── Get import status (for polling) ──────────────────────────────

    public function getImportStatus(int $importId): array
    {
        $log = $this->importLogRepository->find($importId);
        if ($log === null) {
            throw new NotFoundHttpException('Import log not found.');
        }
        return $this->formatLogResponse($log);
    }

    // ── Import history ───────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function getHistory(int $limit = 20): array
    {
        $logs = $this->importLogRepository->findBy([], ['createdAt' => 'DESC'], $limit);

        return array_map(fn (PacaImportLog $l) => $this->formatLogResponse($l), $logs);
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function formatLogResponse(PacaImportLog $l): array
    {
        return [
            'id' => $l->getId(),
            'originalFilename' => $l->getOriginalFilename(),
            'totalRows' => $l->getTotalRows(),
            'processedRows' => $l->getProcessedRows(),
            'createdCount' => $l->getCreatedCount(),
            'updatedCount' => $l->getUpdatedCount(),
            'errorCount' => $l->getErrorCount(),
            'errors' => $l->getErrors(),
            'status' => $l->getStatus(),
            'user' => $l->getUser()->getName() . ' ' . $l->getUser()->getLastName(),
            'createdAt' => $l->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'completedAt' => $l->getCompletedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function applyCellValues(Paca $paca, array $row, array $colMap, array $catalogMap): void
    {
        $desc = $this->getCellValue($row, $colMap, 'descripción', 'descripcion');
        if ($desc !== null) {
            $paca->setDescription($desc !== '' ? $desc : null);
        }

        $purchasePrice = $this->getCellValue($row, $colMap, 'precio compra');
        if ($purchasePrice !== null && $purchasePrice !== '') {
            $paca->setPurchasePrice((string) (float) $purchasePrice);
        }

        $sellingPrice = $this->getCellValue($row, $colMap, 'precio venta');
        if ($sellingPrice !== null && $sellingPrice !== '') {
            $paca->setSellingPrice((string) (float) $sellingPrice);
        }

        $stock = $this->getCellValue($row, $colMap, 'stock');
        if ($stock !== null && $stock !== '') {
            $paca->setStock((int) $stock);
        }

        $pieces = $this->getCellValue($row, $colMap, 'piezas');
        if ($pieces !== null && $pieces !== '') {
            $paca->setPieceCount((int) $pieces);
        }

        $weight = $this->getCellValue($row, $colMap, 'peso (kg)', 'peso');
        if ($weight !== null && $weight !== '') {
            $paca->setWeight((string) (float) $weight);
        }

        $active = $this->getCellValue($row, $colMap, 'activo');
        if ($active !== null && $active !== '') {
            $paca->setActive(\in_array(mb_strtolower($active), ['sí', 'si', '1', 'true', 'yes'], true));
        }

        $this->setRelationByName($paca, 'setSupplier', $this->getCellValue($row, $colMap, 'proveedor'), $catalogMap['suppliers'] ?? []);
        $this->setRelationByName($paca, 'setBrand', $this->getCellValue($row, $colMap, 'marca'), $catalogMap['brands'] ?? []);
        $this->setRelationByName($paca, 'setLabel', $this->getCellValue($row, $colMap, 'etiqueta'), $catalogMap['labels'] ?? []);
        $this->setRelationByName($paca, 'setQualityGrade', $this->getCellValue($row, $colMap, 'calidad'), $catalogMap['qualities'] ?? []);
        $this->setRelationByName($paca, 'setSeason', $this->getCellValue($row, $colMap, 'temporada'), $catalogMap['seasons'] ?? []);
        $this->setRelationByName($paca, 'setGender', $this->getCellValue($row, $colMap, 'género', 'genero'), $catalogMap['genders'] ?? []);
        $this->setRelationByName($paca, 'setGarmentType', $this->getCellValue($row, $colMap, 'tipo prenda'), $catalogMap['garmentTypes'] ?? []);
        $this->setRelationByName($paca, 'setFabricType', $this->getCellValue($row, $colMap, 'tipo tela'), $catalogMap['fabricTypes'] ?? []);
        $this->setRelationByName($paca, 'setSizeProfile', $this->getCellValue($row, $colMap, 'perfil talla'), $catalogMap['sizeProfiles'] ?? []);
    }

    private function getCellValue(array $row, array $colMap, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($colMap[$key])) {
                $val = $row[$colMap[$key]] ?? null;
                return $val !== null ? trim((string) $val) : null;
            }
        }
        return null;
    }

    private function setRelationByName(Paca $paca, string $setter, ?string $name, array $lookup): void
    {
        if ($name === null || $name === '') {
            return;
        }
        $normalized = mb_strtolower($name);
        if (isset($lookup[$normalized])) {
            $paca->$setter($lookup[$normalized]);
        }
    }

    /** @return array<string, array<string, object>> */
    private function buildCatalogLookups(): array
    {
        $map = [];
        $entityClasses = [
            'suppliers' => \App\Entity\Supplier::class,
            'brands' => \App\Entity\Brand::class,
            'labels' => \App\Entity\LabelCatalog::class,
            'qualities' => \App\Entity\QualityGrade::class,
            'seasons' => \App\Entity\SeasonCatalog::class,
            'genders' => \App\Entity\GenderCatalog::class,
            'garmentTypes' => \App\Entity\GarmentType::class,
            'fabricTypes' => \App\Entity\FabricType::class,
            'sizeProfiles' => \App\Entity\SizeProfile::class,
        ];
        foreach ($entityClasses as $key => $class) {
            $entities = $this->em->getRepository($class)->findAll();
            $lookup = [];
            foreach ($entities as $entity) {
                $lookup[mb_strtolower($entity->getName())] = $entity;
            }
            $map[$key] = $lookup;
        }
        return $map;
    }

    private function normalizeImportErrorMessage(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        $lowerMessage = mb_strtolower($message);

        if ($message === '') {
            return 'Ocurrió un error inesperado al procesar la importación.';
        }

        if (str_contains($lowerMessage, 'entitymanager is closed')) {
            return 'La importación se detuvo por un error interno después de procesar una fila. Revise los errores registrados anteriormente para identificar la causa original.';
        }

        if (str_contains($lowerMessage, 'duplicate entry')) {
            if (str_contains($lowerMessage, 'serial')) {
                return 'La paca ya existe y se intentó crear una unidad con un serial repetido. El producto existente debe actualizar stock sin reutilizar seriales ya creados.';
            }

            if (str_contains($lowerMessage, 'code')) {
                return 'Ya existe una paca con el mismo código. Verifique que el código del archivo no esté duplicado.';
            }

            if (str_contains($lowerMessage, 'name')) {
                return 'Ya existe una paca con el mismo nombre. Verifique nombres duplicados en el archivo o en la base de datos.';
            }

            return 'Se detectó un valor duplicado en la base de datos. Revise códigos, nombres y seriales del archivo.';
        }

        if (str_contains($lowerMessage, 'integrity constraint violation') || str_contains($lowerMessage, 'foreign key constraint fails')) {
            return 'No se pudo guardar la información porque una relación requerida no existe o está siendo usada por otros registros.';
        }

        if (str_contains($lowerMessage, 'not null')) {
            return 'Falta un dato obligatorio para guardar una de las filas del archivo.';
        }

        if (str_contains($lowerMessage, 'archivo de importación no encontrado')) {
            return 'El archivo temporal de importación no fue encontrado. Vuelva a subir el archivo e inténtelo de nuevo.';
        }

        if (str_contains($lowerMessage, 'la bodega especificada no existe')) {
            return 'La bodega seleccionada no existe o ya no está disponible.';
        }

        if (str_contains($lowerMessage, 'esta importación ya fue procesada')) {
            return 'Esta importación ya fue procesada anteriormente. Suba un archivo nuevo si desea volver a importar.';
        }

        if (str_contains($lowerMessage, 'el archivo no contiene')) {
            return $message;
        }

        return $message;
    }
}
