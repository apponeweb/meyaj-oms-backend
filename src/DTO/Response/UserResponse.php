<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\User;

final readonly class UserResponse
{
    public int $id;
    public string $name;
    public ?string $lastName;
    public ?string $phone;
    public string $email;
    /** @var list<string> */
    public array $roles;
    public ?int $roleId;
    public ?string $roleName;
    public ?int $companyId;
    public ?string $companyName;
    public ?int $branchId;
    public ?string $branchName;
    public ?int $departmentId;
    public ?string $departmentName;
    public ?string $acronym;
    public ?string $image;
    public bool $active;
    public bool $isMobileAllowed;
    public string $createdAt;

    public function __construct(User $user)
    {
        $this->id = $user->getId();
        $this->name = $user->getName();
        $this->lastName = $user->getLastName();
        $this->phone = $user->getPhone();
        $this->email = $user->getEmail();
        $this->roles = $user->getRoles();
        $this->roleId = $user->getRole()?->getId();
        $this->roleName = $user->getRole()?->getName();
        $this->companyId = $user->getCompany()?->getId();
        $this->companyName = $user->getCompany()?->getName();
        $this->branchId = $user->getBranch()?->getId();
        $this->branchName = $user->getBranch()?->getName();
        $this->departmentId = $user->getDepartment()?->getId();
        $this->departmentName = $user->getDepartment()?->getName();
        $this->acronym = $user->getAcronym();
        $this->image = $user->getImage();
        $this->active = $user->isActive();
        $this->isMobileAllowed = $user->isMobileAllowed();
        $this->createdAt = $user->getCreatedAt()->format(\DateTimeInterface::ATOM);
    }
}
