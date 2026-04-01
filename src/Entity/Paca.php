<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PacaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PacaRepository::class)]
#[ORM\Table(name: 'paca')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Este código de paca ya existe')]
class Paca
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank] #[Assert\Length(min: 2, max: 50)]
    private string $code = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank] #[Assert\Length(min: 2, max: 255)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Brand::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Brand $brand = null;

    #[ORM\ManyToOne(targetEntity: LabelCatalog::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LabelCatalog $label = null;

    #[ORM\ManyToOne(targetEntity: QualityGrade::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?QualityGrade $qualityGrade = null;

    #[ORM\ManyToOne(targetEntity: SeasonCatalog::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SeasonCatalog $season = null;

    #[ORM\ManyToOne(targetEntity: GenderCatalog::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GenderCatalog $gender = null;

    #[ORM\ManyToOne(targetEntity: GarmentType::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GarmentType $garmentType = null;

    #[ORM\ManyToOne(targetEntity: FabricType::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FabricType $fabricType = null;

    #[ORM\ManyToOne(targetEntity: SizeProfile::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SizeProfile $sizeProfile = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Supplier $supplier = null;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Warehouse $warehouse = null;

    #[ORM\ManyToOne(targetEntity: WarehouseBin::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WarehouseBin $warehouseBin = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $purchasePrice = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $sellingPrice = '0.00';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $stock = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $pieceCount = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?string $weight = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getBrand(): ?Brand { return $this->brand; }
    public function setBrand(?Brand $brand): static { $this->brand = $brand; return $this; }
    public function getLabel(): ?LabelCatalog { return $this->label; }
    public function setLabel(?LabelCatalog $label): static { $this->label = $label; return $this; }
    public function getQualityGrade(): ?QualityGrade { return $this->qualityGrade; }
    public function setQualityGrade(?QualityGrade $qualityGrade): static { $this->qualityGrade = $qualityGrade; return $this; }
    public function getSeason(): ?SeasonCatalog { return $this->season; }
    public function setSeason(?SeasonCatalog $season): static { $this->season = $season; return $this; }
    public function getGender(): ?GenderCatalog { return $this->gender; }
    public function setGender(?GenderCatalog $gender): static { $this->gender = $gender; return $this; }
    public function getGarmentType(): ?GarmentType { return $this->garmentType; }
    public function setGarmentType(?GarmentType $garmentType): static { $this->garmentType = $garmentType; return $this; }
    public function getFabricType(): ?FabricType { return $this->fabricType; }
    public function setFabricType(?FabricType $fabricType): static { $this->fabricType = $fabricType; return $this; }
    public function getSizeProfile(): ?SizeProfile { return $this->sizeProfile; }
    public function setSizeProfile(?SizeProfile $sizeProfile): static { $this->sizeProfile = $sizeProfile; return $this; }
    public function getSupplier(): ?Supplier { return $this->supplier; }
    public function setSupplier(?Supplier $supplier): static { $this->supplier = $supplier; return $this; }

    public function getWarehouse(): ?Warehouse { return $this->warehouse; }
    public function setWarehouse(?Warehouse $warehouse): static { $this->warehouse = $warehouse; return $this; }

    public function getWarehouseBin(): ?WarehouseBin { return $this->warehouseBin; }
    public function setWarehouseBin(?WarehouseBin $warehouseBin): static { $this->warehouseBin = $warehouseBin; return $this; }
    public function getPurchasePrice(): string { return $this->purchasePrice; }
    public function setPurchasePrice(string $purchasePrice): static { $this->purchasePrice = $purchasePrice; return $this; }
    public function getSellingPrice(): string { return $this->sellingPrice; }
    public function setSellingPrice(string $sellingPrice): static { $this->sellingPrice = $sellingPrice; return $this; }
    public function getStock(): int { return $this->stock; }
    public function setStock(int $stock): static { $this->stock = $stock; return $this; }
    public function getPieceCount(): ?int { return $this->pieceCount; }
    public function setPieceCount(?int $pieceCount): static { $this->pieceCount = $pieceCount; return $this; }
    public function getWeight(): ?string { return $this->weight; }
    public function setWeight(?string $weight): static { $this->weight = $weight; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
