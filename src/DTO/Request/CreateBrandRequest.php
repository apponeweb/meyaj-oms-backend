<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateBrandRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(min: 2, max: 100)]
        public string $name,
        #[Assert\Length(max: 10)]
        public ?string $acronym = null,
        public ?string $description = null,
    ) {}
}
