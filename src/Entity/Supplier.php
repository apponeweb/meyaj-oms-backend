<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table(name: 'supplier')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Este proveedor ya existe')]
class Supplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 150)]
    private string $name = '';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $contacts = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $taxId = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, SupplierBrand> */
    #[ORM\OneToMany(targetEntity: SupplierBrand::class, mappedBy: 'supplier', cascade: ['persist', 'remove'])]
    private Collection $supplierBrands;

    #[ORM\ManyToMany(targetEntity: LabelCatalog::class)]
    #[ORM\JoinTable(name: 'supplier_label')]
    private Collection $tags;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->supplierBrands = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getContacts(): ?array { return $this->contacts; }
    public function setContacts(?array $contacts): static { $this->contacts = $contacts; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }
    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): static { $this->country = $country; return $this; }
    public function getTaxId(): ?string { return $this->taxId; }
    public function setTaxId(?string $taxId): static { $this->taxId = $taxId; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, SupplierBrand> */
    public function getSupplierBrands(): Collection { return $this->supplierBrands; }

    /** @return Collection<int, LabelCatalog> */
    public function getTags(): Collection { return $this->tags; }

    public function addTag(LabelCatalog $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(LabelCatalog $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }
}
