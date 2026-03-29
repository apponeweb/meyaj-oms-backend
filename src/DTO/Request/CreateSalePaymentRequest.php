<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Entity\Payment;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSalePaymentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public string $amount,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: [Payment::METHOD_CASH, Payment::METHOD_CARD, Payment::METHOD_TRANSFER, Payment::METHOD_MOBILE])]
        public string $method,

        public ?string $transactionId = null,

        public ?string $notes = null,
    ) {
    }
}
