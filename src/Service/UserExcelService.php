<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserImportLog;
use App\Repository\UserImportLogRepository;
use App\Repository\UserRepository;
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserExcelService
{
    private const HEADERS = [
        'Nombre', 'Apellido', 'Email', 'Teléfono', 
        'Rol', 'Empresa', 'Sucursal', 'Departamento', 
        'Acrónimo', 'Activo', 'Acceso Móvil'
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private string $importDir,
    ) {}

    // ── Export ────────────────────────────────────────────────────────

    public function export(): StreamedResponse
    {
        $users = $this->userRepository->createPaginatedQueryBuilder()
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

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
        /** @var User $user */
        foreach ($users as $user) {
            $values = [
                $user->getName(),
                $user->getLastName() ?? '',
                $user->getEmail(),
                $user->getPhone() ?? '',
                $user->getRole()?->getName() ?? '',
                $user->getCompany()?->getName() ?? '',
                $user->getBranch()?->getName() ?? '',
                $user->getDepartment()?->getName() ?? '',
                $user->getAcronym() ?? '',
                $user->isActive() ? 'Sí' : 'No',
                $user->isMobileAllowed() ? 'Sí' : 'No',
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

        $filename = 'usuarios_' . date('Y-m-d_His') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    // ── Import — Phase 1: upload & create log ────────────────────────

    public function uploadImportFile(\SplFileInfo $file, string $originalName, User $user): UserImportLog
    {
        if (!is_dir($this->importDir)) {
            mkdir($this->importDir, 0755, true);
        }

        $storedName = uniqid('import_users_') . '.xlsx';
        copy($file->getPathname(), $this->importDir . '/' . $storedName);

        // Count data rows (exclude header)
        $spreadsheet = IOFactory::load($this->importDir . '/' . $storedName);
        $totalRows = max(0, $spreadsheet->getActiveSheet()->getHighestDataRow() - 1);

        $log = new UserImportLog();
        $log->setFilename($storedName);
        $log->setOriginalFilename($originalName);
        $log->setTotalRows($totalRows);
        $log->setUser($user);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    // ── Import — Phase 2: process (synchronous, saves progress per row) ──

    public function processImport(int $importId): array
    {
        $log = $this->em->getRepository(UserImportLog::class)->find($importId);
        if ($log === null) {
            throw new NotFoundHttpException('Import log not found.');
        }
        if ($log->getStatus() !== UserImportLog::STATUS_PENDING) {
            throw new BadRequestHttpException('Esta importación ya fue procesada.');
        }

        $filePath = $this->importDir . '/' . $log->getFilename();
        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Archivo de importación no encontrado.');
        }

        $log->setStatus(UserImportLog::STATUS_PROCESSING);
        $this->em->flush();

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

            $created = 0;
            $updated = 0;
            $errors = [];
            $processed = 0;

            foreach ($rows as $rowNum => $row) {
                $email = trim((string) ($row[$colMap['email'] ?? ''] ?? ''));
                if ($email === '') {
                    $processed++;
                    $log->setProcessedRows($processed);
                    $this->em->flush();
                    continue;
                }

                $name = trim((string) ($row[$colMap['nombre'] ?? ''] ?? ''));
                if ($name === '') {
                    $errors[] = "Fila {$rowNum}: El nombre es obligatorio para el email '{$email}'.";
                    $processed++;
                    $log->setProcessedRows($processed);
                    $log->setErrors($errors);
                    $log->setErrorCount(\count($errors));
                    $this->em->flush();
                    continue;
                }

                try {
                    $existingUser = $this->userRepository->findOneBy(['email' => $email]);
                    $user = $existingUser ?? new User();

                    $user->setEmail($email);
                    $user->setName($name);
                    
                    if ($existingUser === null) {
                        // Set default password for new users
                        $user->setPassword($this->passwordHasher->hashPassword($user, 'Meyaj123!'));
                    }

                    $this->applyCellValues($user, $row, $colMap, $catalogMap);

                    if ($existingUser === null) {
                        $this->em->persist($user);
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Fila {$rowNum} (email '{$email}'): " . $e->getMessage();
                }

                $processed++;
                $log->setProcessedRows($processed);
                $log->setCreatedCount($created);
                $log->setUpdatedCount($updated);
                $log->setErrors($errors);
                $log->setErrorCount(\count($errors));
                $this->em->flush();
            }

            $log->setStatus(UserImportLog::STATUS_COMPLETED);
            $log->setCompletedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable $e) {
            $log->setStatus(UserImportLog::STATUS_FAILED);
            $log->addError($e->getMessage());
            $log->setCompletedAt(new \DateTimeImmutable());
            $this->em->flush();
            throw $e;
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        return $this->formatLogResponse($log);
    }

    public function getImportStatus(int $importId): array
    {
        $log = $this->em->getRepository(UserImportLog::class)->find($importId);
        if ($log === null) {
            throw new NotFoundHttpException('Import log not found.');
        }
        return $this->formatLogResponse($log);
    }

    /** @return list<array<string, mixed>> */
    public function getHistory(int $limit = 20): array
    {
        $logs = $this->em->getRepository(UserImportLog::class)->findBy([], ['createdAt' => 'DESC'], $limit);
        return array_map(fn (UserImportLog $l) => $this->formatLogResponse($l), $logs);
    }

    private function formatLogResponse(UserImportLog $l): array
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

    private function applyCellValues(User $user, array $row, array $colMap, array $catalogMap): void
    {
        $lastName = $this->getCellValue($row, $colMap, 'apellido');
        if ($lastName !== null) $user->setLastName($lastName !== '' ? $lastName : null);

        $phone = $this->getCellValue($row, $colMap, 'teléfono', 'telefono');
        if ($phone !== null) $user->setPhone($phone !== '' ? $phone : null);

        $acronym = $this->getCellValue($row, $colMap, 'acrónimo', 'acronimo');
        if ($acronym !== null) $user->setAcronym($acronym !== '' ? $acronym : null);

        $active = $this->getCellValue($row, $colMap, 'activo');
        if ($active !== null && $active !== '') {
            $user->setActive(\in_array(mb_strtolower($active), ['sí', 'si', '1', 'true', 'yes'], true));
        }

        $mobile = $this->getCellValue($row, $colMap, 'acceso móvil', 'acceso movil');
        if ($mobile !== null && $mobile !== '') {
            $user->setIsMobileAllowed(\in_array(mb_strtolower($mobile), ['sí', 'si', '1', 'true', 'yes'], true));
        }

        $this->setRelationByName($user, 'setRole', $this->getCellValue($row, $colMap, 'rol'), $catalogMap['roles'] ?? []);
        $this->setRelationByName($user, 'setCompany', $this->getCellValue($row, $colMap, 'empresa'), $catalogMap['companies'] ?? []);
        $this->setRelationByName($user, 'setBranch', $this->getCellValue($row, $colMap, 'sucursal'), $catalogMap['branches'] ?? []);
        $this->setRelationByName($user, 'setDepartment', $this->getCellValue($row, $colMap, 'departamento'), $catalogMap['departments'] ?? []);
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

    private function setRelationByName(User $user, string $setter, ?string $name, array $lookup): void
    {
        if ($name === null || $name === '') return;
        $normalized = mb_strtolower($name);
        if (isset($lookup[$normalized])) {
            $user->$setter($lookup[$normalized]);
        }
    }

    private function buildCatalogLookups(): array
    {
        $map = [];
        $entityClasses = [
            'roles' => \App\Entity\Role::class,
            'companies' => \App\Entity\Company::class,
            'branches' => \App\Entity\Branch::class,
            'departments' => \App\Entity\Department::class,
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
