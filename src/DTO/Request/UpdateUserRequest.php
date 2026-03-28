<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateUserRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 100)]
        public ?string $name = null,

        #[Assert\Email]
        public ?string $email = null,

        #[Assert\Length(min: 6, max: 255)]
        public ?string $password = null,

        /** @var list<string>|null */
        #[Assert\All([
            new Assert\Choice(choices: ['ROLE_USER', 'ROLE_ADMIN']),
        ])]
        public ?array $roles = null,
    ) {
    }
}
