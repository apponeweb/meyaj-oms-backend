<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateAppFunctionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $moduleId,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 50)]
        public string $code,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $name,

        #[Assert\PositiveOrZero]
        public int $displayOrder = 0,
    ) {
    }
}
