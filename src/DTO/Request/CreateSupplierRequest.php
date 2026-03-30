<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSupplierRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(min: 2, max: 150)]
        public string $name,
        public ?array $contacts = null,
        public ?string $address = null,
        public ?string $country = null,
        public ?string $taxId = null,
    ) {}
}
