<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateTagRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 100)] public ?string $name = null,
        public ?string $color = null,
        public ?string $description = null,
        public ?bool $active = null,
    ) {}
}
