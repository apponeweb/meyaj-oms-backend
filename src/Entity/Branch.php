<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BranchRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BranchRepository::class)]
#[ORM\Table(name: 'branch')]
#[ORM\UniqueConstraint(name: 'uniq_branch_company_name', columns: ['company_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['company', 'name'], message: 'Esta sucursal ya existe en esta empresa')]
class Branch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'branches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 150)]
    private string $name = '';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $abbreviations = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $schedule = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private bool $active = true;

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

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): static { $this->company = $company; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getAbbreviations(): ?string { return $this->abbreviations; }
    public function setAbbreviations(?string $abbreviations): static { $this->abbreviations = $abbreviations; return $this; }

    public function getAddress(): ?array { return $this->address; }
    public function setAddress(?array $address): static { $this->address = $address; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getSchedule(): ?string { return $this->schedule; }
    public function setSchedule(?string $schedule): static { $this->schedule = $schedule; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

}
