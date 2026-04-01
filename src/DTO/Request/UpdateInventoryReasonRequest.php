<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateInventoryReasonRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 30)]
        public ?string $code = null,

        #[Assert\Length(min: 2, max: 100)]
        public ?string $name = null,

        #[Assert\Choice(choices: ['IN', 'OUT'])]
        public ?string $direction = null,

        public ?bool $requiresReference = null,
        public ?bool $isActive = null,
    ) {
    }
}
