<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

final readonly class FolioGenerator
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Generates folios like PO-2026-0001, SO-2026-0001, IC-2026-0001
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
}
