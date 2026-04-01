<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'sales_order_item')]
class SalesOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SalesOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SalesOrder $salesOrder;

    #[ORM\ManyToOne(targetEntity: Paca::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Paca $paca;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $discount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $totalPrice = '0.00';

    #[ORM\Column(nullable: true)]
    private ?int $commissionRuleId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }

    public function getSalesOrder(): SalesOrder { return $this->salesOrder; }
    public function setSalesOrder(SalesOrder $salesOrder): static { $this->salesOrder = $salesOrder; return $this; }

    public function getPaca(): Paca { return $this->paca; }
    public function setPaca(Paca $paca): static { $this->paca = $paca; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }

    public function getDiscount(): string { return $this->discount; }
    public function setDiscount(string $discount): static { $this->discount = $discount; return $this; }

    public function getTotalPrice(): string { return $this->totalPrice; }
    public function setTotalPrice(string $totalPrice): static { $this->totalPrice = $totalPrice; return $this; }

    public function getCommissionRuleId(): ?int { return $this->commissionRuleId; }
    public function setCommissionRuleId(?int $commissionRuleId): static { $this->commissionRuleId = $commissionRuleId; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
}
