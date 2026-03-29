<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateActionCatalogRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 50)]
        public ?string $code = null,

        #[Assert\Length(min: 2, max: 100)]
        public ?string $name = null,
    ) {
    }
}
