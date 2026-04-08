<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shipment_order_item')]
#[ORM\Index(columns: ['shipment_order_id'], name: 'idx_shoi_shipment_order')]
#[ORM\Index(columns: ['paca_unit_id'], name: 'idx_shoi_paca_unit')]
class ShipmentOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShipmentOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ShipmentOrder $shipmentOrder;

    #[ORM\ManyToOne(targetEntity: PacaUnit::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private PacaUnit $pacaUnit;

    #[ORM\Column]
    private \DateTimeImmutable $scannedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $scannedBy;

    public function __construct()
    {
        $this->scannedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getShipmentOrder(): ShipmentOrder { return $this->shipmentOrder; }
    public function setShipmentOrder(ShipmentOrder $shipmentOrder): static { $this->shipmentOrder = $shipmentOrder; return $this; }

    public function getPacaUnit(): PacaUnit { return $this->pacaUnit; }
    public function setPacaUnit(PacaUnit $pacaUnit): static { $this->pacaUnit = $pacaUnit; return $this; }

    public function getScannedAt(): \DateTimeImmutable { return $this->scannedAt; }
    public function setScannedAt(\DateTimeImmutable $scannedAt): static { $this->scannedAt = $scannedAt; return $this; }

    public function getScannedBy(): User { return $this->scannedBy; }
    public function setScannedBy(User $scannedBy): static { $this->scannedBy = $scannedBy; return $this; }
}
