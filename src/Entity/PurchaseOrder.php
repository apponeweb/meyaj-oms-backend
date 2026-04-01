<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PurchaseOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PurchaseOrderRepository::class)]
#[ORM\Table(name: 'purchase_order')]
#[ORM\Index(columns: ['company_id'], name: 'idx_po_company')]
#[ORM\Index(columns: ['supplier_id'], name: 'idx_po_supplier')]
#[ORM\Index(columns: ['folio'], name: 'idx_po_folio')]
#[ORM\Index(columns: ['status'], name: 'idx_po_status')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['folio'], message: 'Este folio ya existe')]
class PurchaseOrder
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SENT = 'SENT';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Supplier $supplier;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $folio = '';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['DRAFT', 'SENT', 'PARTIAL', 'RECEIVED', 'CANCELLED'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    private \DateTimeInterface $orderDate;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expectedDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $receivedDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $subtotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $tax = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $discount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $total = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, PurchaseOrderItem> */
    #[ORM\OneToMany(targetEntity: PurchaseOrderItem::class, mappedBy: 'purchaseOrder', cascade: ['persist', 'remove'])]
    private Collection $items;

    public function __construct()
    {
        $this->orderDate = new \DateTime();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): static { $this->company = $company; return $this; }

    public function getSupplier(): Supplier { return $this->supplier; }
    public function setSupplier(Supplier $supplier): static { $this->supplier = $supplier; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getFolio(): string { return $this->folio; }
    public function setFolio(string $folio): static { $this->folio = $folio; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getOrderDate(): \DateTimeInterface { return $this->orderDate; }
    public function setOrderDate(\DateTimeInterface $orderDate): static { $this->orderDate = $orderDate; return $this; }

    public function getExpectedDate(): ?\DateTimeInterface { return $this->expectedDate; }
    public function setExpectedDate(?\DateTimeInterface $expectedDate): static { $this->expectedDate = $expectedDate; return $this; }

    public function getReceivedDate(): ?\DateTimeInterface { return $this->receivedDate; }
    public function setReceivedDate(?\DateTimeInterface $receivedDate): static { $this->receivedDate = $receivedDate; return $this; }

    public function getSubtotal(): string { return $this->subtotal; }
    public function setSubtotal(string $subtotal): static { $this->subtotal = $subtotal; return $this; }

    public function getTax(): string { return $this->tax; }
    public function setTax(string $tax): static { $this->tax = $tax; return $this; }

    public function getDiscount(): string { return $this->discount; }
    public function setDiscount(string $discount): static { $this->discount = $discount; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): static { $this->total = $total; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, PurchaseOrderItem> */
    public function getItems(): Collection { return $this->items; }

    public function addItem(PurchaseOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setPurchaseOrder($this);
        }
        return $this;
    }

    public function removeItem(PurchaseOrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getPurchaseOrder() === $this) {
                $item->setPurchaseOrder($this);
            }
        }
        return $this;
    }
}
