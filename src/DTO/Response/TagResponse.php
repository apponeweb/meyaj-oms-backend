<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Tag;

final readonly class TagResponse
{
    public int $id;
    public string $name;
    public ?string $color;
    public ?string $description;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Tag $t)
    {
        $this->id = $t->getId();
        $this->name = $t->getName();
        $this->color = $t->getColor();
        $this->description = $t->getDescription();
        $this->active = $t->isActive();
        $this->createdAt = $t->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $t->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
