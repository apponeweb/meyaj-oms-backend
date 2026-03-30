<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateBranchRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 150)]
        public ?string $name = null,

        public ?string $abbreviations = null,
        public ?array $address = null,
        public ?string $phone = null,
        public ?string $schedule = null,
        public ?string $image = null,
        public ?bool $active = null,
    ) {
    }
}
