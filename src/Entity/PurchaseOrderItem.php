<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'purchase_order_item')]
class PurchaseOrderItem
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PurchaseOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PurchaseOrder $purchaseOrder;

    #[ORM\ManyToOne(targetEntity: LabelCatalog::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LabelCatalog $label = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $description = '';

    #[ORM\Column]
    #[Assert\Positive]
    private int $expectedQty = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $receivedQty = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $totalPrice = '0.00';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['PENDING', 'PARTIAL', 'RECEIVED', 'CANCELLED'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }

    public function getPurchaseOrder(): PurchaseOrder { return $this->purchaseOrder; }
    public function setPurchaseOrder(PurchaseOrder $purchaseOrder): static { $this->purchaseOrder = $purchaseOrder; return $this; }

    public function getLabel(): ?LabelCatalog { return $this->label; }
    public function setLabel(?LabelCatalog $label): static { $this->label = $label; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getExpectedQty(): int { return $this->expectedQty; }
    public function setExpectedQty(int $expectedQty): static { $this->expectedQty = $expectedQty; return $this; }

    public function getReceivedQty(): int { return $this->receivedQty; }
    public function setReceivedQty(int $receivedQty): static { $this->receivedQty = $receivedQty; return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }

    public function getTotalPrice(): string { return $this->totalPrice; }
    public function setTotalPrice(string $totalPrice): static { $this->totalPrice = $totalPrice; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
}
