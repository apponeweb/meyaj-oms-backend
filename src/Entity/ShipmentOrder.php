<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShipmentOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShipmentOrderRepository::class)]
#[ORM\Table(name: 'shipment_order')]
#[ORM\Index(columns: ['sales_order_id'], name: 'idx_sho_sales_order')]
#[ORM\Index(columns: ['status'], name: 'idx_sho_status')]
#[ORM\Index(columns: ['folio'], name: 'idx_sho_folio')]
#[ORM\Index(columns: ['warehouse_id'], name: 'idx_sho_warehouse')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['folio'], message: 'Este folio de envío ya existe')]
class ShipmentOrder
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PICKING = 'PICKING';
    public const STATUS_PACKED = 'PACKED';
    public const STATUS_SHIPPED = 'SHIPPED';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SalesOrder::class, inversedBy: 'shipments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private SalesOrder $salesOrder;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Warehouse $warehouse;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $folio = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['PENDING', 'PICKING', 'PACKED', 'SHIPPED', 'DELIVERED', 'CANCELLED'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $carrier = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ShipmentOrderItem> */
    #[ORM\OneToMany(targetEntity: ShipmentOrderItem::class, mappedBy: 'shipmentOrder', cascade: ['persist', 'remove'])]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getSalesOrder(): SalesOrder { return $this->salesOrder; }
    public function setSalesOrder(SalesOrder $salesOrder): static { $this->salesOrder = $salesOrder; return $this; }

    public function getWarehouse(): Warehouse { return $this->warehouse; }
    public function setWarehouse(Warehouse $warehouse): static { $this->warehouse = $warehouse; return $this; }

    public function getFolio(): string { return $this->folio; }
    public function setFolio(string $folio): static { $this->folio = $folio; return $this; }

    public function getTrackingNumber(): ?string { return $this->trackingNumber; }
    public function setTrackingNumber(?string $trackingNumber): static { $this->trackingNumber = $trackingNumber; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCarrier(): ?string { return $this->carrier; }
    public function setCarrier(?string $carrier): static { $this->carrier = $carrier; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getShippedAt(): ?\DateTimeImmutable { return $this->shippedAt; }
    public function setShippedAt(?\DateTimeImmutable $shippedAt): static { $this->shippedAt = $shippedAt; return $this; }

    public function getDeliveredAt(): ?\DateTimeImmutable { return $this->deliveredAt; }
    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static { $this->deliveredAt = $deliveredAt; return $this; }

    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, ShipmentOrderItem> */
    public function getItems(): Collection { return $this->items; }

    public function addItem(ShipmentOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setShipmentOrder($this);
        }
        return $this;
    }

    public function removeItem(ShipmentOrderItem $item): static
    {
        $this->items->removeElement($item);
        return $this;
    }
}
