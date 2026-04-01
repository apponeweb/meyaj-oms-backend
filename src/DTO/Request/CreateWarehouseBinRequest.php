<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateWarehouseBinRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public int $warehouseId,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 30)]
        public string $code,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        public string $name,

        #[Assert\Length(max: 50)]
        public ?string $zone = null,

        #[Assert\Length(max: 30)]
        public ?string $binType = null,

        #[Assert\Positive]
        public ?int $capacity = null,
    ) {
    }
}
