<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeasonCatalogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SeasonCatalogRepository::class)]
#[ORM\Table(name: 'season_catalog')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Este registro ya existe')]
class SeasonCatalog
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)] #[Assert\NotBlank] #[Assert\Length(min: 2, max: 100)]
    private string $name = '';

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $acronym = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getAcronym(): ?string { return $this->acronym; }
    public function setAcronym(?string $acronym): static { $this->acronym = $acronym; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
