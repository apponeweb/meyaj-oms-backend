<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Brand;

final readonly class BrandResponse
{
    public int $id;
    public string $name;
    public ?string $acronym;
    public ?string $description;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Brand $b)
    {
        $this->id = $b->getId();
        $this->name = $b->getName();
        $this->acronym = $b->getAcronym();
        $this->description = $b->getDescription();
        $this->active = $b->isActive();
        $this->createdAt = $b->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $b->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
