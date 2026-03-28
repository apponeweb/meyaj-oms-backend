<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateDepartmentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $branchId,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 150)]
        public string $name,

        public ?string $description = null,
    ) {
    }
}
