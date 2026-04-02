<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WarehouseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WarehouseRepository::class)]
#[ORM\Table(name: 'warehouse')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['company_id'], name: 'idx_warehouse_company')]
#[ORM\Index(columns: ['code'], name: 'idx_warehouse_code')]
#[UniqueEntity(fields: ['code'], message: 'Este código de bodega ya existe')]
class Warehouse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 20)]
    private string $code = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: WarehouseType::class, inversedBy: 'warehouses')]
    #[ORM\JoinColumn(nullable: false)]
    private WarehouseType $warehouseType;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $monthlyCost = null;

    #[ORM\Column]
    private bool $isExternal = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, WarehouseBin> */
    #[ORM\OneToMany(targetEntity: WarehouseBin::class, mappedBy: 'warehouse', cascade: ['persist'])]
    private Collection $bins;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->bins = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): static { $this->company = $company; return $this; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getWarehouseType(): WarehouseType { return $this->warehouseType; }
    public function setWarehouseType(WarehouseType $warehouseType): static { $this->warehouseType = $warehouseType; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    public function getMonthlyCost(): ?string { return $this->monthlyCost; }
    public function setMonthlyCost(?string $monthlyCost): static { $this->monthlyCost = $monthlyCost; return $this; }

    public function isExternal(): bool { return $this->isExternal; }
    public function setIsExternal(bool $isExternal): static { $this->isExternal = $isExternal; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, WarehouseBin> */
    public function getBins(): Collection { return $this->bins; }
}
