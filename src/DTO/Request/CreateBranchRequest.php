<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateBranchRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $companyId,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 150)]
        public string $name,

        public ?string $code = null,
        public ?array $address = null,
        public ?string $phone = null,
        public ?string $image = null,
    ) {
    }
}
