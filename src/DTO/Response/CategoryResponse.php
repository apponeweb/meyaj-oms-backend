<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Category;

final readonly class CategoryResponse
{
    public int $id;
    public string $name;
    public ?string $acronym;
    public ?string $description;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Category $category)
    {
        $this->id = $category->getId();
        $this->name = $category->getName();
        $this->acronym = $category->getAcronym();
        $this->description = $category->getDescription();
        $this->createdAt = $category->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $category->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
