<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Entity\Sale;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSaleRequest
{
    /**
     * @param CreateSaleItemRequest[] $items
     * @param CreateSalePaymentRequest[] $payments
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD, Sale::PAYMENT_TRANSFER, Sale::PAYMENT_MIXED])]
        public string $paymentMethod,

        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $items,

        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $payments,

        #[Assert\Positive]
        public ?int $customerId = null,

        public ?string $notes = null,

        #[Assert\PositiveOrZero]
        public ?string $discountAmount = null,
    ) {
    }
}
