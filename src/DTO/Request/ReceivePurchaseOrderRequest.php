<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReceivePurchaseOrderRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public int $warehouseId,

        #[Assert\NotBlank]
        #[Assert\Count(min: 1, minMessage: 'Debe incluir al menos un artículo a recibir.')]
        /** @var array<array{itemId: int, receivedQty: int, notes?: string}> */
        public array $items = [],
    ) {}
}
