<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateWarehouseBinRequest
{
    public function __construct(
        #[Assert\Length(min: 1, max: 30)]
        public ?string $code = null,

        #[Assert\Length(min: 1, max: 100)]
        public ?string $name = null,

        #[Assert\Length(max: 50)]
        public ?string $zone = null,

        #[Assert\Length(max: 30)]
        public ?string $binType = null,

        #[Assert\Positive]
        public ?int $capacity = null,

        public ?bool $isActive = null,
    ) {
    }
}
