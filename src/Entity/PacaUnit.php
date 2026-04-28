<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PacaUnitRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: PacaUnitRepository::class)]
#[ORM\Table(name: 'paca_unit')]
#[ORM\Index(columns: ['paca_id', 'status'], name: 'idx_paca_unit_paca_status')]
#[ORM\Index(columns: ['warehouse_id'], name: 'idx_paca_unit_warehouse')]
#[ORM\Index(columns: ['status'], name: 'idx_paca_unit_status')]
#[ORM\Index(columns: ['sales_order_id'], name: 'idx_paca_unit_so')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['serial'], message: 'Este serial ya existe')]
class PacaUnit
{
    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_RESERVED = 'RESERVED';
    public const STATUS_PICKED = 'PICKED';
    public const STATUS_DISPATCHED = 'DISPATCHED';
    public const STATUS_SOLD = 'SOLD';
    public const STATUS_RETURNED = 'RETURNED';
    public const STATUS_DAMAGED = 'DAMAGED';

    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $serial = '';

    #[ORM\ManyToOne(targetEntity: Paca::class, inversedBy: 'units')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Paca $paca;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\ManyToOne(targetEntity: WarehouseBin::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WarehouseBin $warehouseBin = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_AVAILABLE;

    #[ORM\ManyToOne(targetEntity: SalesOrder::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SalesOrder $salesOrder = null;

    #[ORM\ManyToOne(targetEntity: SalesOrderItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SalesOrderItem $salesOrderItem = null;

    #[ORM\ManyToOne(targetEntity: PurchaseOrder::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PurchaseOrder $purchaseOrder = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $labeledAt = null;

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

    public function getSerial(): string { return $this->serial; }
    public function setSerial(string $serial): static { $this->serial = $serial; return $this; }

    public function getPaca(): Paca { return $this->paca; }
    public function setPaca(Paca $paca): static { $this->paca = $paca; return $this; }

    public function getWarehouse(): Warehouse { return $this->warehouse; }
    public function setWarehouse(Warehouse $warehouse): static { $this->warehouse = $warehouse; return $this; }

    public function getWarehouseBin(): ?WarehouseBin { return $this->warehouseBin; }
    public function setWarehouseBin(?WarehouseBin $warehouseBin): static { $this->warehouseBin = $warehouseBin; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getSalesOrder(): ?SalesOrder { return $this->salesOrder; }
    public function setSalesOrder(?SalesOrder $salesOrder): static { $this->salesOrder = $salesOrder; return $this; }

    public function getSalesOrderItem(): ?SalesOrderItem { return $this->salesOrderItem; }
    public function setSalesOrderItem(?SalesOrderItem $salesOrderItem): static { $this->salesOrderItem = $salesOrderItem; return $this; }

    public function getPurchaseOrder(): ?PurchaseOrder { return $this->purchaseOrder; }
    public function setPurchaseOrder(?PurchaseOrder $purchaseOrder): static { $this->purchaseOrder = $purchaseOrder; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getLabeledAt(): ?\DateTimeImmutable { return $this->labeledAt; }
    public function markAsLabeled(): static { $this->labeledAt = new \DateTimeImmutable(); return $this; }
    public function isLabeled(): bool { return $this->labeledAt !== null; }

    public function isAvailable(): bool { return $this->status === self::STATUS_AVAILABLE; }
    public function isReserved(): bool { return $this->status === self::STATUS_RESERVED; }
}
