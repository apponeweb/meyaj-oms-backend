<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateWarehouseRequest
{
    public function __construct(
        public ?int $companyId = null,

        #[Assert\Length(min: 2, max: 20)]
        public ?string $code = null,

        #[Assert\Length(min: 2, max: 100)]
        public ?string $name = null,

        public ?int $warehouseTypeId = null,

        public ?string $address = null,

        #[Assert\PositiveOrZero]
        public ?string $monthlyCost = null,

        public ?bool $isExternal = null,
        public ?bool $isActive = null,
    ) {
    }
}
