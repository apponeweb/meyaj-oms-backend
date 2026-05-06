<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Brand;
use App\Entity\BrandImportLog;
use App\Entity\User;
use App\Repository\BrandRepository;
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

final readonly class BrandExcelService
{
    private const HEADERS = [
        'Nombre',
        'Siglas',
        'Descripción',
        'Activo'
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private BrandRepository $brandRepository,
        private string $importDir,
    ) {
    }

    public function export(): StreamedResponse
    {
        $brands = $this->brandRepository->findBy([], ['name' => 'ASC']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Marcas');

        // Headers
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

        // Data
        $row = 2;
        foreach ($brands as $brand) {
            $sheet->setCellValue('A' . $row, $brand->getName());
            $sheet->setCellValue('B' . $row, $brand->getAcronym() ?? '');
            $sheet->setCellValue('C' . $row, $brand->getDescription() ?? '');
            $sheet->setCellValue('D' . $row, $brand->isActive() ? 'Sí' : 'No');
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="marcas_' . date('Y-m-d') . '.xlsx"');

        return $response;
    }

    public function uploadImportFile(\SplFileInfo $file, string $originalFilename, User $user): BrandImportLog
    {
        if (!file_exists($this->importDir)) {
            mkdir($this->importDir, 0777, true);
        }

        $filename = uniqid('brand_import_', true) . '.xlsx';
        $filepath = $this->importDir . '/' . $filename;

        move_uploaded_file($file->getPathname(), $filepath);

        $spreadsheet = IOFactory::load($filepath);
        $worksheet = $spreadsheet->getActiveSheet();
        $totalRows = $worksheet->getHighestDataRow() - 1; // Subtract header row

        if ($totalRows <= 0) {
            throw new BadRequestHttpException('El archivo no contiene datos válidos');
        }

        $log = new BrandImportLog();
        $log->setOriginalFilename($originalFilename);
        $log->setFilename($filename);
        $log->setTotalRows($totalRows);
        $log->setStatus('pending');
        $log->setUser($user);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    public function processImport(int $importId): BrandImportLog
    {
        $log = $this->em->find(BrandImportLog::class, $importId);
        if (!$log) {
            throw new NotFoundHttpException('Log de importación no encontrado');
        }

        if ($log->getStatus() === 'processing') {
            throw new BadRequestHttpException('La importación ya está en proceso');
        }

        $log->setStatus('processing');
        $this->em->flush();

        $filepath = $this->importDir . '/' . $log->getFilename();
        if (!file_exists($filepath)) {
            $log->setStatus('failed');
            $log->addError('Archivo de importación no encontrado');
            $this->em->flush();
            throw new NotFoundHttpException('Archivo de importación no encontrado');
        }

        try {
            $spreadsheet = IOFactory::load($filepath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $createdCount = 0;
            $updatedCount = 0;
            $errorCount = 0;
            $errors = [];

            // Process each row
            for ($row = 2; $row <= $worksheet->getHighestDataRow(); $row++) {
                try {
                    $name = trim($worksheet->getCell('A' . $row)->getValue() ?? '');
                    if (empty($name)) {
                        $errors[] = "Fila {$row}: El nombre es requerido";
                        $errorCount++;
                        continue;
                    }

                    $acronym = trim($worksheet->getCell('B' . $row)->getValue() ?? '');
                    $description = trim($worksheet->getCell('C' . $row)->getValue() ?? '');
                    $activeStr = trim($worksheet->getCell('D' . $row)->getValue() ?? '');
                    $active = in_array(strtolower($activeStr), ['sí', 'si', 'yes', '1', 'true', 'activo', 'active']);

                    // Default siglas: first 3 characters of name if not provided
                    if (empty($acronym)) {
                        $acronym = strtoupper(substr($name, 0, 3));
                    }

                    // Check if brand already exists by name
                    $existingBrand = $this->brandRepository->findOneBy(['name' => $name]);

                    if ($existingBrand) {
                        // Update existing brand
                        $existingBrand->setAcronym($acronym);
                        $existingBrand->setDescription(empty($description) ? null : $description);
                        $existingBrand->setActive($active);
                        $updatedCount++;
                    } else {
                        // Create new brand
                        $brand = new Brand();
                        $brand->setName($name);
                        $brand->setAcronym($acronym);
                        $brand->setDescription(empty($description) ? null : $description);
                        $brand->setActive($active);
                        $this->em->persist($brand);
                        $createdCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Fila {$row}: " . $e->getMessage();
                    $errorCount++;
                }
            }

            // Save all changes
            $this->em->flush();

            // Update log
            $log->setProcessedRows($worksheet->getHighestDataRow() - 1);
            $log->setCreatedCount($createdCount);
            $log->setUpdatedCount($updatedCount);
            $log->setErrorCount($errorCount);
            $log->setErrors($errors);
            $log->setStatus('completed');
            $this->em->flush();

            return $log;

        } catch (\Exception $e) {
            $log->setStatus('failed');
            $log->addError('Error procesando archivo: ' . $e->getMessage());
            $this->em->flush();
            throw $e;
        }
    }

    public function getImportStatus(int $importId): ?BrandImportLog
    {
        return $this->em->find(BrandImportLog::class, $importId);
    }

    public function getHistory(): array
    {
        return $this->em->getRepository(BrandImportLog::class)
            ->findBy([], ['createdAt' => 'DESC']);
    }
}
