<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateDepartmentRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 150)]
        public ?string $name = null,

        #[Assert\Length(max: 20)]
        public ?string $acronym = null,

        public ?string $description = null,
        public ?bool $active = null,
    ) {
    }
}
