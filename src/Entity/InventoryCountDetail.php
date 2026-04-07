<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inventory_count_detail')]
class InventoryCountDetail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryCount::class, inversedBy: 'details')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InventoryCount $inventoryCount;

    #[ORM\ManyToOne(targetEntity: Paca::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Paca $paca;

    #[ORM\Column]
    private int $systemQty = 0;

    #[ORM\Column(nullable: true)]
    private ?int $countedQty = null;

    #[ORM\Column(nullable: true)]
    private ?int $difference = null;

    #[ORM\Column(length: 20)]
    private string $status = 'PENDING';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $countedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $firstCountedQty = null;

    #[ORM\Column(nullable: true)]
    private ?int $firstDifference = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $firstCountedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getInventoryCount(): InventoryCount { return $this->inventoryCount; }
    public function setInventoryCount(InventoryCount $inventoryCount): static { $this->inventoryCount = $inventoryCount; return $this; }

    public function getPaca(): Paca { return $this->paca; }
    public function setPaca(Paca $paca): static { $this->paca = $paca; return $this; }

    public function getSystemQty(): int { return $this->systemQty; }
    public function setSystemQty(int $systemQty): static { $this->systemQty = $systemQty; return $this; }

    public function getCountedQty(): ?int { return $this->countedQty; }
    public function setCountedQty(?int $countedQty): static { $this->countedQty = $countedQty; return $this; }

    public function getDifference(): ?int { return $this->difference; }
    public function setDifference(?int $difference): static { $this->difference = $difference; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCountedAt(): ?\DateTimeImmutable { return $this->countedAt; }
    public function setCountedAt(?\DateTimeImmutable $countedAt): static { $this->countedAt = $countedAt; return $this; }

    public function getFirstCountedQty(): ?int { return $this->firstCountedQty; }
    public function setFirstCountedQty(?int $firstCountedQty): static { $this->firstCountedQty = $firstCountedQty; return $this; }

    public function getFirstDifference(): ?int { return $this->firstDifference; }
    public function setFirstDifference(?int $firstDifference): static { $this->firstDifference = $firstDifference; return $this; }

    public function getFirstCountedAt(): ?\DateTimeImmutable { return $this->firstCountedAt; }
    public function setFirstCountedAt(?\DateTimeImmutable $firstCountedAt): static { $this->firstCountedAt = $firstCountedAt; return $this; }
}
