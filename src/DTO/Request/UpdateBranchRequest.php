<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateBranchRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 150)]
        public ?string $name = null,

        public ?string $code = null,
        public ?string $address = null,
        public ?string $phone = null,
        public ?bool $active = null,
    ) {
    }
}
