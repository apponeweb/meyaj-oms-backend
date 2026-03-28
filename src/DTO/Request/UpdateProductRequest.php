<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProductRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 255)]
        public ?string $name = null,

        public ?string $description = null,

        #[Assert\Positive]
        public ?string $price = null,

        #[Assert\PositiveOrZero]
        public ?int $stock = null,

        public ?int $categoryId = null,

        public ?bool $active = null,
    ) {
    }
}
