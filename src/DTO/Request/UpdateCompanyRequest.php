<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateCompanyRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 150)]
        public ?string $name = null,

        public ?string $tradeName = null,
        public ?string $taxId = null,
        public ?string $address = null,
        public ?string $phone = null,

        #[Assert\Email]
        public ?string $email = null,

        public ?bool $active = null,
    ) {
    }
}
