<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SalesOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SalesOrderRepository::class)]
#[ORM\Table(name: 'sales_order')]
#[ORM\Index(columns: ['company_id'], name: 'idx_so_company')]
#[ORM\Index(columns: ['customer_id'], name: 'idx_so_customer')]
#[ORM\Index(columns: ['folio'], name: 'idx_so_folio')]
#[ORM\Index(columns: ['status'], name: 'idx_so_status')]
#[ORM\Index(columns: ['channel'], name: 'idx_so_channel')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['folio'], message: 'Este folio ya existe')]
class SalesOrder
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_PREPARING = 'PREPARING';
    public const STATUS_SHIPPED = 'SHIPPED';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_RETURNED = 'RETURNED';

    public const PAYMENT_PENDING = 'PENDING';
    public const PAYMENT_PARTIAL = 'PARTIAL';
    public const PAYMENT_PAID = 'PAID';
    public const PAYMENT_REFUNDED = 'REFUNDED';

    public const DELIVERY_PENDING = 'PENDING';
    public const DELIVERY_PREPARING = 'PREPARING';
    public const DELIVERY_SHIPPED = 'SHIPPED';
    public const DELIVERY_DELIVERED = 'DELIVERED';

    public const CHANNEL_POS = 'POS';
    public const CHANNEL_WEB = 'WEB';
    public const CHANNEL_WHATSAPP = 'WHATSAPP';
    public const CHANNEL_PHONE = 'PHONE';

    public const TYPE_STANDARD = 'STANDARD';
    public const TYPE_EXPRESS = 'EXPRESS';
    public const TYPE_WHOLESALE = 'WHOLESALE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Branch $branch = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Customer $customer;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customerAddress = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $seller = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $folio = '';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['POS', 'WEB', 'WHATSAPP', 'PHONE'])]
    private string $channel = self::CHANNEL_POS;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['STANDARD', 'EXPRESS', 'WHOLESALE'])]
    private string $orderType = self::TYPE_STANDARD;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['PENDING', 'CONFIRMED', 'PREPARING', 'SHIPPED', 'DELIVERED', 'CANCELLED', 'RETURNED'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['PENDING', 'PARTIAL', 'PAID', 'REFUNDED'])]
    private string $paymentStatus = self::PAYMENT_PENDING;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['PENDING', 'PREPARING', 'SHIPPED', 'DELIVERED'])]
    private string $deliveryStatus = self::DELIVERY_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $subtotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $tax = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $discount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $shippingCost = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $total = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $sourceWhatsapp = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $estimatedDelivery = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, SalesOrderItem> */
    #[ORM\OneToMany(targetEntity: SalesOrderItem::class, mappedBy: 'salesOrder', cascade: ['persist', 'remove'])]
    private Collection $items;

    /** @var Collection<int, SalesOrderStatusHistory> */
    #[ORM\OneToMany(targetEntity: SalesOrderStatusHistory::class, mappedBy: 'salesOrder', cascade: ['persist', 'remove'])]
    private Collection $statusHistory;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): static { $this->company = $company; return $this; }

    public function getBranch(): ?Branch { return $this->branch; }
    public function setBranch(?Branch $branch): static { $this->branch = $branch; return $this; }

    public function getCustomer(): Customer { return $this->customer; }
    public function setCustomer(Customer $customer): static { $this->customer = $customer; return $this; }

    public function getCustomerAddress(): ?string { return $this->customerAddress; }
    public function setCustomerAddress(?string $customerAddress): static { $this->customerAddress = $customerAddress; return $this; }

    public function getSeller(): ?User { return $this->seller; }
    public function setSeller(?User $seller): static { $this->seller = $seller; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getFolio(): string { return $this->folio; }
    public function setFolio(string $folio): static { $this->folio = $folio; return $this; }

    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $channel): static { $this->channel = $channel; return $this; }

    public function getOrderType(): string { return $this->orderType; }
    public function setOrderType(string $orderType): static { $this->orderType = $orderType; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function setPaymentStatus(string $paymentStatus): static { $this->paymentStatus = $paymentStatus; return $this; }

    public function getDeliveryStatus(): string { return $this->deliveryStatus; }
    public function setDeliveryStatus(string $deliveryStatus): static { $this->deliveryStatus = $deliveryStatus; return $this; }

    public function getSubtotal(): string { return $this->subtotal; }
    public function setSubtotal(string $subtotal): static { $this->subtotal = $subtotal; return $this; }

    public function getTax(): string { return $this->tax; }
    public function setTax(string $tax): static { $this->tax = $tax; return $this; }

    public function getDiscount(): string { return $this->discount; }
    public function setDiscount(string $discount): static { $this->discount = $discount; return $this; }

    public function getShippingCost(): string { return $this->shippingCost; }
    public function setShippingCost(string $shippingCost): static { $this->shippingCost = $shippingCost; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): static { $this->total = $total; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getSourceWhatsapp(): ?string { return $this->sourceWhatsapp; }
    public function setSourceWhatsapp(?string $sourceWhatsapp): static { $this->sourceWhatsapp = $sourceWhatsapp; return $this; }

    public function getEstimatedDelivery(): ?\DateTimeInterface { return $this->estimatedDelivery; }
    public function setEstimatedDelivery(?\DateTimeInterface $estimatedDelivery): static { $this->estimatedDelivery = $estimatedDelivery; return $this; }

    public function getDeliveredAt(): ?\DateTimeImmutable { return $this->deliveredAt; }
    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static { $this->deliveredAt = $deliveredAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, SalesOrderItem> */
    public function getItems(): Collection { return $this->items; }

    public function addItem(SalesOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSalesOrder($this);
        }
        return $this;
    }

    public function removeItem(SalesOrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getSalesOrder() === $this) {
                $item->setSalesOrder($this);
            }
        }
        return $this;
    }

    /** @return Collection<int, SalesOrderStatusHistory> */
    public function getStatusHistory(): Collection { return $this->statusHistory; }

    public function addStatusHistory(SalesOrderStatusHistory $history): static
    {
        if (!$this->statusHistory->contains($history)) {
            $this->statusHistory->add($history);
            $history->setSalesOrder($this);
        }
        return $this;
    }
}
