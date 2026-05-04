<?php

declare(strict_types=1);

namespace App\Service;

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

        // Mark as processing using direct SQL so it commits immediately without opening
        // a long-lived Doctrine transaction that would block concurrent /status polls.
        $conn = $this->em->getConnection();
        $conn->executeStatement(
            "UPDATE paca_import_log SET status = 'processing' WHERE id = :id",
            ['id' => $log->getId()],
        );
        // Detach the log entity so Doctrine never touches it again; all progress
        // updates go through flushProgress() which uses direct SQL.
        $this->em->detach($log);

        $created = 0;
        $updated = 0;
        $errors = [];
        $processed = 0;

        // Keep warehouse as a plain reference; re-fetch if EM gets cleared mid-loop.
        $warehouseId2 = $warehouse->getId();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (\count($rows) < 2) {
                throw new \RuntimeException('El archivo no contiene datos.');
            }

            $headerRow = $rows[1] ?? [];
            unset($rows[1]);

            $colMap = [];
            foreach ($headerRow as $letter => $value) {
                $colMap[mb_strtolower(trim((string) $value))] = $letter;
            }

            $catalogMap = $this->buildCatalogLookups();

            $existingNames = [];
            foreach ($this->pacaRepository->findAll() as $existing) {
                $existingNames[mb_strtolower($existing->getName())] = $existing->getCode();
            }
            $namesInFile = [];

            foreach ($rows as $rowNum => $row) {
                $code = trim((string) ($row[$colMap['código'] ?? $colMap['codigo'] ?? ''] ?? ''));
                if ($code === '') {
                    $processed++;
                    $this->flushProgress($log->getId(), $processed, $created, $updated, $errors);
                    continue;
                }

                $name = trim((string) ($row[$colMap['nombre'] ?? ''] ?? ''));
                if ($name === '') {
                    $errors[] = "Fila {$rowNum}: El nombre es obligatorio para el código '{$code}'.";
                    $processed++;
                    $this->flushProgress($log->getId(), $processed, $created, $updated, $errors);
                    continue;
                }

                $nameLower = mb_strtolower($name);
                if (isset($namesInFile[$nameLower]) && $namesInFile[$nameLower] !== $code) {
                    $errors[] = "Fila {$rowNum}: El nombre '{$name}' está duplicado en el archivo (ya en código '{$namesInFile[$nameLower]}').";
                    $processed++;
                    $this->flushProgress($log->getId(), $processed, $created, $updated, $errors);
                    continue;
                }

                if (isset($existingNames[$nameLower]) && $existingNames[$nameLower] !== $code) {
                    $errors[] = "Fila {$rowNum}: El nombre '{$name}' ya existe en la BD (código '{$existingNames[$nameLower]}').";
                    $processed++;
                    $this->flushProgress($log->getId(), $processed, $created, $updated, $errors);
                    continue;
                }

                $namesInFile[$nameLower] = $code;

                try {
                    // Re-fetch warehouse after any EM clear so it stays managed.
                    $warehouse = $this->warehouseRepository->find($warehouseId2);

                    $existingPaca = $this->pacaRepository->findOneBy(['code' => $code]);
                    $isNew = $existingPaca === null;
                    $paca = $existingPaca ?? new Paca();

                    $paca->setCode($code);
                    $paca->setName($name);
                    $this->applyCellValues($paca, $row, $colMap, $catalogMap);

                    if ($isNew) {
                        $this->em->persist($paca);
                    }

                    $this->syncUnitsForImport($paca, $warehouse, $isNew, $replaceUnits);

                    // flush commits this row; Doctrine manages its own transaction here.
                    $this->em->flush();

                    // Only count after a successful flush.
                    if ($isNew) {
                        $created++;
                        $existingNames[$nameLower] = $code;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Fila {$rowNum} (código '{$code}'): " . $e->getMessage();
                    // Clear only the pending unit-of-work; detached entities (log) stay gone.
                    $this->em->clear();
                }

                $processed++;
                $this->flushProgress($log->getId(), $processed, $created, $updated, $errors);
            }

            $this->flushProgress($log->getId(), $processed, $created, $updated, $errors, PacaImportLog::STATUS_COMPLETED, new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $this->flushProgress($log->getId(), $processed, $created, $updated, $errors, PacaImportLog::STATUS_FAILED, new \DateTimeImmutable(), $e->getMessage());
            throw $e;
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Re-fetch log fresh from DB (flushProgress wrote via raw SQL, ORM object is detached).
        $freshLog = $this->importLogRepository->find($importId);
        return $this->formatLogResponse($freshLog ?? $log);
    }

    /**
     * Writes progress directly via SQL so concurrent /status polls see it immediately,
     * bypassing Doctrine's identity map and any open ORM transaction.
     *
     * @param string[] $errors
     */
    private function flushProgress(
        int $logId,
        int $processed,
        int $created,
        int $updated,
        array $errors,
        string $status = PacaImportLog::STATUS_PROCESSING,
        ?\DateTimeImmutable $completedAt = null,
        ?string $extraError = null,
    ): void {
        if ($extraError !== null) {
            $errors[] = $extraError;
        }

        $conn = $this->em->getConnection();
        $conn->executeStatement(
            'UPDATE paca_import_log
             SET status = :status,
                 processed_rows = :processed,
                 created_count  = :created,
                 updated_count  = :updated,
                 error_count    = :errorCount,
                 errors         = :errors,
                 completed_at   = :completedAt
             WHERE id = :id',
            [
                'id'          => $logId,
                'status'      => $status,
                'processed'   => $processed,
                'created'     => $created,
                'updated'     => $updated,
                'errorCount'  => \count($errors),
                'errors'      => json_encode($errors, \JSON_UNESCAPED_UNICODE),
                'completedAt' => $completedAt?->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function syncUnitsForImport(Paca $paca, Warehouse $warehouse, bool $isNew, bool $replaceUnits = false): void
    {
        $stock = $paca->getCachedStock();

        if ($isNew) {
            if ($stock <= 0) {
                return;
            }
            $this->createUnitsForImport($paca, $warehouse, $stock, 0);
            return;
        }

        if ($replaceUnits) {
            // Delete all AVAILABLE units and recreate from serial 1
            $this->em->createQuery(
                "DELETE FROM App\Entity\PacaUnit u WHERE u.paca = :paca AND u.status = 'AVAILABLE'"
            )->setParameter('paca', $paca)->execute();

            if ($stock > 0) {
                $this->createUnitsForImport($paca, $warehouse, $stock, 0);
            }
            return;
        }

        // Continue mode: add units to reach desired stock, continuing from highest serial
        if ($stock <= 0) {
            return;
        }

        $totalUnits = (int) $this->em->createQuery(
            'SELECT COUNT(u.id) FROM App\Entity\PacaUnit u WHERE u.paca = :paca'
        )->setParameter('paca', $paca)->getSingleScalarResult();

        $availableUnits = (int) $this->em->createQuery(
            "SELECT COUNT(u.id) FROM App\Entity\PacaUnit u WHERE u.paca = :paca AND u.status = 'AVAILABLE'"
        )->setParameter('paca', $paca)->getSingleScalarResult();

        $delta = $stock - $availableUnits;
        if ($delta > 0) {
            $this->createUnitsForImport($paca, $warehouse, $delta, $totalUnits);
        }
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
            $paca->setPurchasePrice((string) (float) str_replace(',', '.', $purchasePrice));
        }

        $sellingPrice = $this->getCellValue($row, $colMap, 'precio venta');
        if ($sellingPrice !== null && $sellingPrice !== '') {
            $paca->setSellingPrice((string) (float) str_replace(',', '.', $sellingPrice));
        }

        $stock = $this->getCellValue($row, $colMap, 'stock');
        if ($stock !== null && $stock !== '') {
            $paca->setStock((int) str_replace(',', '.', $stock));
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
}
