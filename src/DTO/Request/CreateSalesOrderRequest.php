<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSalesOrderRequest
{
    /**
     * @param array<array{pacaId: int, quantity: int, unitPrice: string, discount?: string, notes?: string}> $items
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $companyId,

        #[Assert\NotNull]
        #[Assert\Positive]
        public int $customerId,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['POS', 'WEB', 'WHATSAPP', 'PHONE'])]
        public string $channel,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['STANDARD', 'EXPRESS', 'WHOLESALE'])]
        public string $orderType,

        #[Assert\NotBlank]
        #[Assert\Count(min: 1, minMessage: 'Debe incluir al menos un artículo.')]
        public array $items = [],

        #[Assert\Positive]
        public ?int $branchId = null,

        #[Assert\Positive]
        public ?int $sellerId = null,

        public ?string $customerAddress = null,

        public ?string $notes = null,

        public ?string $sourceWhatsapp = null,

        public ?string $estimatedDelivery = null,
    ) {}
}
