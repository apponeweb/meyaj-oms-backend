<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInventoryReservationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public int $pacaId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $quantity,

        public ?int $salesOrderId = null,

        public ?int $salesOrderItemId = null,

        public ?string $expiresAt = null,
    ) {
    }
}
