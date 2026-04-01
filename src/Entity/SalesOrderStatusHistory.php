<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SalesOrderStatusHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalesOrderStatusHistoryRepository::class)]
#[ORM\Table(name: 'sales_order_status_history')]
class SalesOrderStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SalesOrder::class, inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SalesOrder $salesOrder;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $fromStatus = null;

    #[ORM\Column(length: 30)]
    private string $toStatus = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSalesOrder(): SalesOrder { return $this->salesOrder; }
    public function setSalesOrder(SalesOrder $salesOrder): static { $this->salesOrder = $salesOrder; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getFromStatus(): ?string { return $this->fromStatus; }
    public function setFromStatus(?string $fromStatus): static { $this->fromStatus = $fromStatus; return $this; }

    public function getToStatus(): string { return $this->toStatus; }
    public function setToStatus(string $toStatus): static { $this->toStatus = $toStatus; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
