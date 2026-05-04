<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LabelCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class LabelCatalogImportService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   processed: int,
     *   errors: list<string>
     * }
     */
    public function importFromFile(UploadedFile $file): array
    {
        $allowed = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-office',
        ];

        $mimeType = $file->getMimeType();
        if ($mimeType !== null && !in_array($mimeType, $allowed, true)) {
            throw new BadRequestHttpException('El archivo debe ser Excel o CSV.');
        }

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            throw new BadRequestHttpException('El archivo está vacío o no contiene filas de datos.');
        }

        $headerRow = $rows[1] ?? [];
        unset($rows[1]);

        $colMap = [];
        foreach ($headerRow as $letter => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if ($normalized !== '') {
                $colMap[$normalized] = $letter;
            }
        }

        if (!isset($colMap['nombre']) && !isset($colMap['name'])) {
            throw new BadRequestHttpException('El archivo debe incluir la columna "nombre" o "name".');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $processed = 0;
        $errors = [];

        foreach ($rows as $rowNum => $row) {
            $name = $this->getCellValue($row, $colMap, 'nombre', 'name');
            $name = $name !== null ? trim($name) : '';

            if ($name === '') {
                $skipped++;
                continue;
            }

            try {
                /** @var LabelCatalog|null $label */
                $label = $this->em->getRepository(LabelCatalog::class)->findOneBy(['name' => $name]);
                $isNew = $label === null;
                $label ??= new LabelCatalog();

                $label->setName($name);

                $acronym = $this->getCellValue($row, $colMap, 'sigla', 'acrónimo', 'acronimo', 'acronym');
                if ($acronym !== null) {
                    $acronym = trim($acronym);
                    $label->setAcronym($acronym !== '' ? $acronym : null);
                }

                $description = $this->getCellValue($row, $colMap, 'descripcion', 'descripción', 'description');
                if ($description !== null) {
                    $description = trim($description);
                    $label->setDescription($description !== '' ? $description : null);
                }

                $active = $this->getCellValue($row, $colMap, 'activo', 'active');
                if ($active !== null && trim($active) !== '') {
                    $label->setActive($this->parseBoolean($active));
                }

                if ($isNew) {
                    $this->em->persist($label);
                    $created++;
                } else {
                    $updated++;
                }

                $this->em->flush();
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = sprintf('Fila %d (%s): %s', $rowNum, $name, $e->getMessage());
                $this->em->clear();
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'processed' => $processed,
            'errors' => $errors,
        ];
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $value);

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $colMap
     */
    private function getCellValue(array $row, array $colMap, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeHeader($key);
            $column = $colMap[$normalizedKey] ?? null;
            if ($column === null) {
                continue;
            }

            $value = $row[$column] ?? null;
            if ($value === null) {
                return null;
            }

            return trim((string) $value);
        }

        return null;
    }

    private function parseBoolean(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'si', 'sí', 'activo'], true);
    }
}
