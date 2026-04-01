<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePacaRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(min: 2, max: 50)]
        public string $code,
        #[Assert\NotBlank] #[Assert\Length(min: 2, max: 255)]
        public string $name,
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
        #[Assert\PositiveOrZero]
        public string $purchasePrice = '0.00',
        #[Assert\PositiveOrZero]
        public string $sellingPrice = '0.00',
        #[Assert\PositiveOrZero]
        public int $stock = 0,
        public ?int $pieceCount = null,
        public ?string $weight = null,
        public ?int $warehouseId = null,
        public ?int $warehouseBinId = null,
    ) {}
}
