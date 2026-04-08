<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdatePacaRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 50)] public ?string $code = null,
        #[Assert\Length(min: 2, max: 255)] public ?string $name = null,
        public ?string $description = null,
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
        public ?int $pieceCount = null,
        public ?string $weight = null,
        public ?bool $active = null,
    ) {}
}
