<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateUserRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $name,
        
        #[Assert\Length(max: 100)]
        public ?string $lastName = null,

        #[Assert\Length(max: 20)]
        public ?string $phone = null,

        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,

        #[Assert\NotBlank]
        #[Assert\Length(min: 6, max: 255)]
        public string $password,

        public array $roles = [],

        #[Assert\Positive]
        public ?int $roleId = null,

        public ?string $image = null,

        public bool $active = true,
    ) {
    }
}
