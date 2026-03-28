<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        public string $name,

        public ?string $description = null,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public string $price = '0.00',

        #[Assert\PositiveOrZero]
        public int $stock = 0,

        public ?int $categoryId = null,

        public bool $active = true,
    ) {
    }
}
