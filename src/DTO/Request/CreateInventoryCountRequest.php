<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInventoryCountRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public int $companyId,

        #[Assert\NotBlank]
        public int $warehouseId,

        #[Assert\NotBlank]
        public string $countDate,

        public ?string $notes = null,
    ) {
    }
}
