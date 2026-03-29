<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateActionCatalogRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 50)]
        public string $code,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $name,
    ) {
    }
}
