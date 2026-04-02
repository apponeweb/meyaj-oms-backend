<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateWarehouseRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public int $companyId,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 20)]
        public string $code,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $name,

        #[Assert\NotBlank]
        public int $warehouseTypeId,

        public ?string $address = null,

        #[Assert\PositiveOrZero]
        public ?string $monthlyCost = null,

        public bool $isExternal = false,
    ) {
    }
}
