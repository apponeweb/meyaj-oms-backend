<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePurchaseOrderRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $companyId,

        #[Assert\NotNull]
        #[Assert\Positive]
        public int $supplierId,

        #[Assert\NotBlank]
        public string $orderDate,

        public ?string $expectedDate = null,

        public ?string $notes = null,

        #[Assert\NotBlank]
        #[Assert\Count(min: 1, minMessage: 'Debe incluir al menos un artículo.')]
        /** @var array<array{description: string, expectedQty: int, unitPrice: string, labelId?: int, pacaId?: int}> */
        public array $items = [],
    ) {}
}
