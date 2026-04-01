<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeStatusRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $status,

        public ?string $notes = null,
    ) {}
}
