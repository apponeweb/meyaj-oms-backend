<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InventoryReasonRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryReasonRepository::class)]
#[ORM\Table(name: 'inventory_reason')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Este código de motivo ya existe')]
class InventoryReason
{
    public const CODE_PURCHASE     = 'PURCHASE';
    public const CODE_SALE         = 'SALE';
    public const CODE_RETURN       = 'RETURN';
    public const CODE_RESERVE      = 'RESERVE';
    public const CODE_RELEASE      = 'RELEASE';
    public const CODE_LOSS         = 'LOSS';
    public const CODE_TRANSFER_IN  = 'TRANSFER_IN';
    public const CODE_TRANSFER_OUT = 'TRANSFER_OUT';
    public const CODE_ADJ_IN       = 'ADJUSTMENT_IN';
    public const CODE_ADJ_OUT      = 'ADJUSTMENT_OUT';
    public const CODE_PHYSICAL     = 'PHYSICAL_RECEIPT';

    /** Códigos que el sistema usa internamente y no pueden modificarse */
    public const SYSTEM_CODES = [
        self::CODE_PURCHASE,
        self::CODE_SALE,
        self::CODE_RETURN,
        self::CODE_RESERVE,
        self::CODE_RELEASE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 30)]
    private string $code = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name = '';

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['IN', 'OUT'])]
    private string $direction = 'IN';

    #[ORM\Column]
    private bool $requiresReference = false;

    #[ORM\Column]
    private bool $isActive = true;

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

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDirection(): string { return $this->direction; }
    public function setDirection(string $direction): static { $this->direction = $direction; return $this; }

    public function isRequiresReference(): bool { return $this->requiresReference; }
    public function setRequiresReference(bool $requiresReference): static { $this->requiresReference = $requiresReference; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
