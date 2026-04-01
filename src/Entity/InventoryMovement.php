<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InventoryMovementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMovementRepository::class)]
#[ORM\Table(name: 'inventory_movement')]
#[ORM\Index(columns: ['paca_id'], name: 'idx_movement_paca')]
#[ORM\Index(columns: ['warehouse_id'], name: 'idx_movement_warehouse')]
#[ORM\Index(columns: ['created_at'], name: 'idx_movement_date')]
#[ORM\Index(columns: ['reference_type', 'reference_id'], name: 'idx_movement_reference')]
class InventoryMovement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Paca::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Paca $paca;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Warehouse $warehouse;

    #[ORM\ManyToOne(targetEntity: WarehouseBin::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WarehouseBin $warehouseBin = null;

    #[ORM\ManyToOne(targetEntity: InventoryReason::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private InventoryReason $reason;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(length: 3)]
    private string $movementType = 'IN';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $referenceType = null;

    #[ORM\Column(nullable: true)]
    private ?int $referenceId = null;

    #[ORM\Column]
    private int $qtyIn = 0;

    #[ORM\Column]
    private int $qtyOut = 0;

    #[ORM\Column]
    private int $balanceAfter = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $unitCost = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): static { $this->company = $company; return $this; }

    public function getPaca(): Paca { return $this->paca; }
    public function setPaca(Paca $paca): static { $this->paca = $paca; return $this; }

    public function getWarehouse(): Warehouse { return $this->warehouse; }
    public function setWarehouse(Warehouse $warehouse): static { $this->warehouse = $warehouse; return $this; }

    public function getWarehouseBin(): ?WarehouseBin { return $this->warehouseBin; }
    public function setWarehouseBin(?WarehouseBin $warehouseBin): static { $this->warehouseBin = $warehouseBin; return $this; }

    public function getReason(): InventoryReason { return $this->reason; }
    public function setReason(InventoryReason $reason): static { $this->reason = $reason; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getMovementType(): string { return $this->movementType; }
    public function setMovementType(string $movementType): static { $this->movementType = $movementType; return $this; }

    public function getReferenceType(): ?string { return $this->referenceType; }
    public function setReferenceType(?string $referenceType): static { $this->referenceType = $referenceType; return $this; }

    public function getReferenceId(): ?int { return $this->referenceId; }
    public function setReferenceId(?int $referenceId): static { $this->referenceId = $referenceId; return $this; }

    public function getQtyIn(): int { return $this->qtyIn; }
    public function setQtyIn(int $qtyIn): static { $this->qtyIn = $qtyIn; return $this; }

    public function getQtyOut(): int { return $this->qtyOut; }
    public function setQtyOut(int $qtyOut): static { $this->qtyOut = $qtyOut; return $this; }

    public function getBalanceAfter(): int { return $this->balanceAfter; }
    public function setBalanceAfter(int $balanceAfter): static { $this->balanceAfter = $balanceAfter; return $this; }

    public function getUnitCost(): ?string { return $this->unitCost; }
    public function setUnitCost(?string $unitCost): static { $this->unitCost = $unitCost; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
