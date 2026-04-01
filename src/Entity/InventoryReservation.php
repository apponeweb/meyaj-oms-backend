<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InventoryReservationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryReservationRepository::class)]
#[ORM\Table(name: 'inventory_reservation')]
#[ORM\Index(columns: ['paca_id'], name: 'idx_reservation_paca')]
#[ORM\Index(columns: ['status'], name: 'idx_reservation_status')]
#[ORM\HasLifecycleCallbacks]
class InventoryReservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $salesOrderId = null;

    #[ORM\Column(nullable: true)]
    private ?int $salesOrderItemId = null;

    #[ORM\ManyToOne(targetEntity: Paca::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Paca $paca;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(length: 20)]
    private string $status = 'ACTIVE';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

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

    public function getSalesOrderId(): ?int { return $this->salesOrderId; }
    public function setSalesOrderId(?int $salesOrderId): static { $this->salesOrderId = $salesOrderId; return $this; }

    public function getSalesOrderItemId(): ?int { return $this->salesOrderItemId; }
    public function setSalesOrderItemId(?int $salesOrderItemId): static { $this->salesOrderItemId = $salesOrderItemId; return $this; }

    public function getPaca(): Paca { return $this->paca; }
    public function setPaca(Paca $paca): static { $this->paca = $paca; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
