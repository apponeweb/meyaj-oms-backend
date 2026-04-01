<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInventoryMovementRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public int $pacaId,

        #[Assert\NotBlank]
        public int $warehouseId,

        public ?int $warehouseBinId = null,

        #[Assert\NotBlank]
        public int $reasonId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $quantity,

        #[Assert\PositiveOrZero]
        public ?string $unitCost = null,

        public ?string $notes = null,
    ) {
    }
}
