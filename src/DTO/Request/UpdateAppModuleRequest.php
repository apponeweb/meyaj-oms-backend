<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateAppModuleRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 50)]
        public ?string $code = null,

        #[Assert\Length(min: 2, max: 100)]
        public ?string $name = null,

        #[Assert\Length(max: 50)]
        public ?string $icon = null,

        #[Assert\PositiveOrZero]
        public ?int $displayOrder = null,

        public ?bool $active = null,
    ) {
    }
}
