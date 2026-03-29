<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSaleItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $productId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $quantity,

        #[Assert\PositiveOrZero]
        public string $discountAmount = '0.00',
    ) {
    }
}
