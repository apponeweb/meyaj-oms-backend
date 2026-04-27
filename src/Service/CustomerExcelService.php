<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\CustomerImportLog;
use App\Entity\User;
use App\Repository\CustomerRepository;
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

final readonly class CustomerExcelService
{
    private const HEADERS = [
        'Nombre', 'Email', 'Teléfono', 'Dirección', 'RFC / Tax ID', 'Activo'
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private CustomerRepository $customerRepository,
        private string $importDir,
    ) {}

    public function export(): StreamedResponse
    {
        $customers = $this->customerRepository->findBy([], ['name' => 'ASC']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clientes');

        foreach (self::HEADERS as $col => $header) {
            $letter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$letter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(\count(self::HEADERS));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']], // Blue for customers
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        /** @var Customer $c */
        foreach ($customers as $c) {
            $values = [
                $c->getName(),
                $c->getEmail() ?? '',
                $c->getPhone() ?? '',
                $c->getAddress() ?? '',
                $c->getTaxId() ?? '',
                $c->isActive() ? 'Sí' : 'No',
            ];
            foreach ($values as $col => $value) {
                $letter = Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue("{$letter}{$rowNum}", $value);
            }
            $rowNum++;
        }

        foreach (range(1, \count(self::HEADERS)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });

        $filename = 'clientes_' . date('Y-m-d_His') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return $response;
    }

    public function uploadImportFile(\SplFileInfo $file, string $originalName, User $user): CustomerImportLog
    {
        if (!is_dir($this->importDir)) mkdir($this->importDir, 0755, true);
        $storedName = uniqid('import_customers_') . '.xlsx';
        copy($file->getPathname(), $this->importDir . '/' . $storedName);

        $spreadsheet = IOFactory::load($this->importDir . '/' . $storedName);
        $totalRows = max(0, $spreadsheet->getActiveSheet()->getHighestDataRow() - 1);

        $log = new CustomerImportLog();
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
        $log = $this->em->getRepository(CustomerImportLog::class)->find($importId);
        if (!$log || $log->getStatus() !== CustomerImportLog::STATUS_PENDING) {
            throw new BadRequestHttpException('Importación no válida o ya procesada.');
        }

        $filePath = $this->importDir . '/' . $log->getFilename();
        if (!file_exists($filePath)) throw new NotFoundHttpException('Archivo no encontrado.');

        $log->setStatus(CustomerImportLog::STATUS_PROCESSING);
        $this->em->flush();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (\count($rows) < 2) throw new \RuntimeException('El archivo está vacío.');

            $headerRow = $rows[1] ?? [];
            unset($rows[1]);

            $colMap = [];
            foreach ($headerRow as $letter => $value) {
                $colMap[mb_strtolower(trim((string) $value))] = $letter;
            }

            $created = 0; $updated = 0; $errors = []; $processed = 0;

            foreach ($rows as $rowNum => $row) {
                $name = trim((string) ($row[$colMap['nombre'] ?? ''] ?? ''));
                if ($name === '') {
                    $processed++;
                    $log->setProcessedRows($processed);
                    $this->em->flush();
                    continue;
                }

                try {
                    $customer = $this->customerRepository->findOneBy(['name' => $name]) ?? new Customer();
                    $isNew = $customer->getId() === null;
                    $customer->setName($name);

                    if ($isNew) $this->em->persist($customer);

                    $email = $this->getCellValue($row, $colMap, 'email', 'correo');
                    if ($email !== null) $customer->setEmail($email !== '' ? $email : null);

                    $phone = $this->getCellValue($row, $colMap, 'teléfono', 'telefono', 'phone');
                    if ($phone !== null) $customer->setPhone($phone !== '' ? $phone : null);

                    $address = $this->getCellValue($row, $colMap, 'dirección', 'direccion');
                    if ($address !== null) $customer->setAddress($address !== '' ? $address : null);

                    $taxId = $this->getCellValue($row, $colMap, 'rfc / tax id', 'rfc', 'tax id', 'taxid');
                    if ($taxId !== null) $customer->setTaxId($taxId !== '' ? $taxId : null);

                    $active = $this->getCellValue($row, $colMap, 'activo');
                    if ($active !== null && $active !== '') {
                        $customer->setActive(\in_array(mb_strtolower($active), ['sí', 'si', '1', 'true', 'yes'], true));
                    }

                    if ($isNew) $created++; else $updated++;
                    
                    $this->em->flush();
                } catch (\Throwable $e) {
                    $errors[] = "Fila {$rowNum} ('{$name}'): " . $e->getMessage();
                    if (!$this->em->isOpen()) throw $e;
                }

                $processed++;
                $log->setProcessedRows($processed);
                $log->setCreatedCount($created);
                $log->setUpdatedCount($updated);
                $log->setErrors($errors);
                $log->setErrorCount(\count($errors));
                $this->em->flush();
            }

            $log->setStatus(CustomerImportLog::STATUS_COMPLETED);
            $log->setCompletedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable $e) {
            if ($this->em->isOpen()) {
                $log->setStatus(CustomerImportLog::STATUS_FAILED);
                $log->addError($e->getMessage());
                $log->setCompletedAt(new \DateTimeImmutable());
                $this->em->flush();
            }
            throw $e;
        } finally {
            if (file_exists($filePath)) unlink($filePath);
        }

        return $this->formatLogResponse($log);
    }

    public function getImportStatus(int $importId): array
    {
        $log = $this->em->getRepository(CustomerImportLog::class)->find($importId);
        if (!$log) throw new NotFoundHttpException('Log no encontrado.');
        return $this->formatLogResponse($log);
    }

    public function getHistory(int $limit = 20): array
    {
        $logs = $this->em->getRepository(CustomerImportLog::class)->findBy([], ['createdAt' => 'DESC'], $limit);
        return array_map(fn(CustomerImportLog $l) => $this->formatLogResponse($l), $logs);
    }

    private function formatLogResponse(CustomerImportLog $l): array
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
            'user' => $l->getUser()->getName(),
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
