<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

final readonly class FolioGenerator
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Generates folios like PV-2026-0001, ENV-2026-0001
     */
    public function generate(string $prefix, string $entityClass, string $folioField = 'folio'): string
    {
        $year = date('Y');
        $pattern = $prefix . '-' . $year . '-%';

        $qb = $this->em->createQueryBuilder()
            ->select("MAX(e.{$folioField})")
            ->from($entityClass, 'e')
            ->where("e.{$folioField} LIKE :pattern")
            ->setParameter('pattern', $pattern);

        $lastFolio = $qb->getQuery()->getSingleScalarResult();

        if ($lastFolio === null) {
            $nextNumber = 1;
        } else {
            $parts = explode('-', $lastFolio);
            $nextNumber = (int) end($parts) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }

    /**
     * Generates folios like OC-07042026-0001 (prefix-ddmmyyyy-incremental)
     */
    public function generateWithDate(string $prefix, string $entityClass, string $folioField = 'folio'): string
    {
        $dateStr = date('dmY');
        $pattern = $prefix . '-' . $dateStr . '-%';

        $qb = $this->em->createQueryBuilder()
            ->select("MAX(e.{$folioField})")
            ->from($entityClass, 'e')
            ->where("e.{$folioField} LIKE :pattern")
            ->setParameter('pattern', $pattern);

        $lastFolio = $qb->getQuery()->getSingleScalarResult();

        if ($lastFolio === null) {
            $nextNumber = 1;
        } else {
            $parts = explode('-', $lastFolio);
            $nextNumber = (int) end($parts) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $dateStr, $nextNumber);
    }
}
