<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InventoryCountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryCountRepository::class)]
#[ORM\Table(name: 'inventory_count')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['folio'], message: 'Este folio de conteo ya existe')]
class InventoryCount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Warehouse $warehouse;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 30)]
    private string $folio = '';

    #[ORM\Column(length: 20)]
    private string $status = 'DRAFT';

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $countDate;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private int $totalItems = 0;

    #[ORM\Column]
    private int $discrepancies = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, InventoryCountDetail> */
    #[ORM\OneToMany(targetEntity: InventoryCountDetail::class, mappedBy: 'inventoryCount', cascade: ['persist', 'remove'])]
    private Collection $details;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->countDate = new \DateTime();
        $this->details = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): static { $this->company = $company; return $this; }

    public function getWarehouse(): Warehouse { return $this->warehouse; }
    public function setWarehouse(Warehouse $warehouse): static { $this->warehouse = $warehouse; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getFolio(): string { return $this->folio; }
    public function setFolio(string $folio): static { $this->folio = $folio; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCountDate(): \DateTimeInterface { return $this->countDate; }
    public function setCountDate(\DateTimeInterface $countDate): static { $this->countDate = $countDate; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getTotalItems(): int { return $this->totalItems; }
    public function setTotalItems(int $totalItems): static { $this->totalItems = $totalItems; return $this; }

    public function getDiscrepancies(): int { return $this->discrepancies; }
    public function setDiscrepancies(int $discrepancies): static { $this->discrepancies = $discrepancies; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, InventoryCountDetail> */
    public function getDetails(): Collection { return $this->details; }

    public function addDetail(InventoryCountDetail $detail): static
    {
        if (!$this->details->contains($detail)) {
            $this->details->add($detail);
            $detail->setInventoryCount($this);
        }
        return $this;
    }

    public function removeDetail(InventoryCountDetail $detail): static
    {
        $this->details->removeElement($detail);
        return $this;
    }
}
