<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Supplier;
use App\Entity\SupplierBrand;
use App\Entity\SupplierImportLog;
use App\Entity\User;
use App\Repository\BrandRepository;
use App\Repository\LabelCatalogRepository;
use App\Repository\SupplierRepository;
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

final readonly class SupplierExcelService
{
    private const HEADERS = [
        'Nombre',
        'Dirección',
        'País',
        'RFC / Tax ID',
        'Activo',
        'Contacto Nombre',
        'Contacto Teléfono',
        'Marcas',
        'Etiquetas'
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private SupplierRepository $supplierRepository,
        private BrandRepository $brandRepository,
        private LabelCatalogRepository $tagRepository,
        private string $importDir,
    ) {
    }

    public function export(): StreamedResponse
    {
        $suppliers = $this->supplierRepository->findBy([], ['name' => 'ASC']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Proveedores');

        foreach (self::HEADERS as $col => $header) {
            $letter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$letter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(\count(self::HEADERS));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']], // Blue
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        /** @var Supplier $s */
        foreach ($suppliers as $s) {
            $contact = ($s->getContacts() && count($s->getContacts()) > 0) ? $s->getContacts()[0] : null;
            $brands = implode(', ', array_map(fn($sb) => $sb->getBrand()->getName(), $s->getSupplierBrands()->toArray()));
            $tags = implode(', ', array_map(fn($t) => $t->getName(), $s->getTags()->toArray()));

            $values = [
                $s->getName(),
                $s->getAddress() ?? '',
                $s->getCountry() ?? '',
                $s->getTaxId() ?? '',
                $s->isActive() ? 'Sí' : 'No',
                $contact['name'] ?? '',
                $contact['phone'] ?? '',
                $brands,
                $tags,
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

        $filename = 'proveedores_' . date('Y-m-d_His') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    public function uploadImportFile(\SplFileInfo $file, string $originalName, User $user): SupplierImportLog
    {
        if (!is_dir($this->importDir)) {
            mkdir($this->importDir, 0755, true);
        }

        $storedName = uniqid('import_suppliers_') . '.xlsx';
        copy($file->getPathname(), $this->importDir . '/' . $storedName);

        $spreadsheet = IOFactory::load($this->importDir . '/' . $storedName);
        $totalRows = max(0, $spreadsheet->getActiveSheet()->getHighestDataRow() - 1);

        $log = new SupplierImportLog();
        $log->setFilename($storedName);
        $log->setOriginalFilename($originalName);
        $log->setTotalRows($totalRows);
        $log->setUser($user);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    public function processImport(int $importId): array
    {
        $log = $this->em->getRepository(SupplierImportLog::class)->find($importId);
        if ($log === null) {
            throw new NotFoundHttpException('Log de importación no encontrado.');
        }
        if ($log->getStatus() !== SupplierImportLog::STATUS_PENDING) {
            throw new BadRequestHttpException('Esta importación ya fue procesada.');
        }

        $filePath = $this->importDir . '/' . $log->getFilename();
        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Archivo no encontrado.');
        }

        $log->setStatus(SupplierImportLog::STATUS_PROCESSING);
        $this->em->flush();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (\count($rows) < 2) {
                throw new \RuntimeException('El archivo está vacío.');
            }

            $headerRow = $rows[1] ?? [];
            unset($rows[1]);

            $colMap = [];
            foreach ($headerRow as $letter => $value) {
                $colMap[mb_strtolower(trim((string) $value))] = $letter;
            }

            $created = 0;
            $updated = 0;
            $errors = [];
            $processed = 0;

            foreach ($rows as $rowNum => $row) {
                $name = trim((string) ($row[$colMap['nombre'] ?? ''] ?? ''));
                if ($name === '') {
                    $processed++;
                    $log->setProcessedRows($processed);
                    $this->em->flush();
                    continue;
                }

                try {
                    $supplier = $this->supplierRepository->findOneBy(['name' => $name]) ?? new Supplier();
                    $isNew = $supplier->getId() === null;
                    $supplier->setName($name);

                    // Persist early to avoid issues with relations
                    if ($isNew) {
                        $this->em->persist($supplier);
                    }

                    $address = $this->getCellValue($row, $colMap, 'dirección', 'direccion');
                    if ($address !== null)
                        $supplier->setAddress($address !== '' ? $address : null);

                    $country = $this->getCellValue($row, $colMap, 'país', 'pais');
                    if ($country !== null)
                        $supplier->setCountry($country !== '' ? $country : null);

                    $taxId = $this->getCellValue($row, $colMap, 'rfc / tax id', 'rfc', 'tax id', 'taxid');
                    if ($taxId !== null)
                        $supplier->setTaxId($taxId !== '' ? $taxId : null);

                    $active = $this->getCellValue($row, $colMap, 'activo');
                    if ($active !== null && $active !== '') {
                        $supplier->setActive(\in_array(mb_strtolower($active), ['sí', 'si', '1', 'true', 'yes'], true));
                    }

                    // --- Contacts ---
                    $contactName = $this->getCellValue($row, $colMap, 'contacto nombre', 'contacto');
                    $contactPhone = $this->getCellValue($row, $colMap, 'contacto teléfono', 'contacto telefono', 'teléfono', 'telefono');
                    if ($contactName || $contactPhone) {
                        $contacts = $supplier->getContacts() ?? [];
                        if (count($contacts) > 0) {
                            if ($contactName)
                                $contacts[0]['name'] = $contactName;
                            if ($contactPhone)
                                $contacts[0]['phone'] = $contactPhone;
                        } else {
                            $contacts[] = ['name' => $contactName ?? '', 'phone' => $contactPhone ?? ''];
                        }
                        $supplier->setContacts($contacts);
                    }

                    // --- Brands ---
                    $brandsStr = $this->getCellValue($row, $colMap, 'marcas', 'marca');
                    if ($brandsStr !== null) {
                        $brandNames = array_unique(array_filter(array_map('trim', explode(',', $brandsStr))));
                        
                        // 1. Identify target brands (create if missing)
                        $targetBrands = [];
                        foreach ($brandNames as $bName) {
                            $brand = $this->brandRepository->findOneBy(['name' => $bName]);
                            if (!$brand) {
                                $brand = new \App\Entity\Brand();
                                $brand->setName($bName);
                                $this->em->persist($brand);
                                $this->em->flush(); // Flush brand immediately to have ID if needed
                            }
                            $targetBrands[$brand->getId()] = $brand;
                        }

                        // 2. Remove those NOT in target
                        foreach ($supplier->getSupplierBrands() as $sb) {
                            if (!isset($targetBrands[$sb->getBrand()->getId()])) {
                                $this->em->remove($sb);
                                $supplier->getSupplierBrands()->removeElement($sb);
                            }
                        }

                        // 3. Add those NOT already present
                        $currentBrandIds = [];
                        foreach ($supplier->getSupplierBrands() as $sb) {
                            $currentBrandIds[] = $sb->getBrand()->getId();
                        }

                        foreach ($targetBrands as $brandId => $brand) {
                            if (!in_array($brandId, $currentBrandIds)) {
                                $sb = new SupplierBrand();
                                $sb->setSupplier($supplier);
                                $sb->setBrand($brand);
                                $this->em->persist($sb);
                                $supplier->getSupplierBrands()->add($sb);
                            }
                        }
                    }

                    // --- Tags ---
                    $tagsStr = $this->getCellValue($row, $colMap, 'etiquetas', 'etiqueta', 'tags');
                    if ($tagsStr !== null) {
                        $supplier->getTags()->clear();
                        $tagNames = array_unique(array_filter(array_map('trim', explode(',', $tagsStr))));
                        foreach ($tagNames as $tName) {
                            $tag = $this->tagRepository->findOneBy(['name' => $tName]);
                            if (!$tag) {
                                $tag = new \App\Entity\LabelCatalog();
                                $tag->setName($tName);
                                $this->em->persist($tag);
                            }
                            $supplier->addTag($tag);
                        }
                    }

                    if ($isNew) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $this->em->flush(); // Flush per row to keep things clean
                } catch (\Throwable $e) {
                    $errors[] = "Fila {$rowNum} ('{$name}'): " . $e->getMessage();
                    // If EM is closed, we can't continue anyway, but we try to log what happened
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

            $log->setStatus(SupplierImportLog::STATUS_COMPLETED);
            $log->setCompletedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable $e) {
            if ($this->em->isOpen()) {
                $log->setStatus(SupplierImportLog::STATUS_FAILED);
                $log->addError($e->getMessage());
                $log->setCompletedAt(new \DateTimeImmutable());
                $this->em->flush();
            }
            throw $e;
        } finally {
            if (file_exists($filePath))
                unlink($filePath);
        }

        return $this->formatLogResponse($log);
    }

    public function getImportStatus(int $importId): array
    {
        $log = $this->em->getRepository(SupplierImportLog::class)->find($importId);
        if ($log === null)
            throw new NotFoundHttpException('Log no encontrado.');
        return $this->formatLogResponse($log);
    }

    public function getHistory(int $limit = 20): array
    {
        $logs = $this->em->getRepository(SupplierImportLog::class)->findBy([], ['createdAt' => 'DESC'], $limit);
        return array_map(fn(SupplierImportLog $l) => $this->formatLogResponse($l), $logs);
    }

    private function formatLogResponse(SupplierImportLog $l): array
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
}
