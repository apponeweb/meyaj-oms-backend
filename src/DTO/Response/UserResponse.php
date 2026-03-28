<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\User;

final readonly class UserResponse
{
    public int $id;
    public string $name;
    public string $email;
    /** @var list<string> */
    public array $roles;
    public string $createdAt;

    public function __construct(User $user)
    {
        $this->id = $user->getId();
        $this->name = $user->getName();
        $this->email = $user->getEmail();
        $this->roles = $user->getRoles();
        $this->createdAt = $user->getCreatedAt()->format(\DateTimeInterface::ATOM);
    }
}
