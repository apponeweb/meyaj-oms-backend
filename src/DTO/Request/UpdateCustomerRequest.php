<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateCustomerRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 255)]
        public ?string $name = null,

        #[Assert\Email]
        public ?string $email = null,

        #[Assert\Length(max: 20)]
        public ?string $phone = null,

        public ?string $address = null,

        #[Assert\Length(max: 50)]
        public ?string $taxId = null,
    ) {
    }
}
