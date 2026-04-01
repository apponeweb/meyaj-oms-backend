<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WarehouseBinRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WarehouseBinRepository::class)]
#[ORM\Table(name: 'warehouse_bin')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['warehouse_id'], name: 'idx_bin_warehouse')]
#[ORM\Index(columns: ['code'], name: 'idx_bin_code')]
#[UniqueEntity(fields: ['warehouse', 'code'], message: 'Este código de ubicación ya existe en esta bodega')]
class WarehouseBin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Warehouse::class, inversedBy: 'bins')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Warehouse $warehouse;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 30)]
    private string $code = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private string $name = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $zone = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $binType = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $capacity = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getWarehouse(): Warehouse { return $this->warehouse; }
    public function setWarehouse(Warehouse $warehouse): static { $this->warehouse = $warehouse; return $this; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getZone(): ?string { return $this->zone; }
    public function setZone(?string $zone): static { $this->zone = $zone; return $this; }

    public function getBinType(): ?string { return $this->binType; }
    public function setBinType(?string $binType): static { $this->binType = $binType; return $this; }

    public function getCapacity(): ?int { return $this->capacity; }
    public function setCapacity(?int $capacity): static { $this->capacity = $capacity; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
