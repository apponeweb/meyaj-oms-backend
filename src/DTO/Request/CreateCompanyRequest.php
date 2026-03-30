<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCompanyRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 150)]
        public string $name,

        public ?string $acronym = null,
        public ?string $tradeName = null,
        public ?string $legalName = null,
        public ?string $tagline = null,
        public ?string $description = null,
        public ?string $taxId = null,
        public ?array $address = null,
        public ?string $phone = null,

        #[Assert\Email]
        public ?string $email = null,
        public ?string $image = null,
        public ?array $socialNetworks = null,
    ) {
    }
}
