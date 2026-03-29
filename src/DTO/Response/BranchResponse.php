<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Branch;

final readonly class BranchResponse
{
    public int $id;
    public int $companyId;
    public string $companyName;
    public string $name;
    public ?string $code;
    public ?array $address;
    public ?string $phone;
    public ?string $image;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Branch $branch)
    {
        $this->id = $branch->getId();
        $this->companyId = $branch->getCompany()->getId();
        $this->companyName = $branch->getCompany()->getName();
        $this->name = $branch->getName();
        $this->code = $branch->getCode();
        $this->address = $branch->getAddress();
        $this->phone = $branch->getPhone();
        $this->image = $branch->getImage();
        $this->active = $branch->isActive();
        $this->createdAt = $branch->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $branch->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
