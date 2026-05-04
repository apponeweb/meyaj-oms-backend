<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BulkUpdatePacaRequest
{
    /**
     * @param list<int> $pacaIds
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\Positive(),
        ])]
        public array $pacaIds = [],
        public ?int $brandId = null,
        public ?int $labelId = null,
        public ?int $qualityGradeId = null,
        public ?int $seasonId = null,
        public ?int $genderId = null,
        public ?int $garmentTypeId = null,
        public ?int $fabricTypeId = null,
        public ?int $sizeProfileId = null,
        public ?int $supplierId = null,
        public ?string $purchasePrice = null,
        public ?string $sellingPrice = null,
    ) {
    }
}
