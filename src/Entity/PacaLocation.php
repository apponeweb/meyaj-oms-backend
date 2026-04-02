<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PacaLocationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PacaLocationRepository::class)]
#[ORM\Table(name: 'paca_location')]
#[ORM\HasLifecycleCallbacks]
class PacaLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Paca::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Paca $paca;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\ManyToOne(targetEntity: WarehouseBin::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WarehouseBin $warehouseBin = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getPaca(): Paca { return $this->paca; }
    public function setPaca(Paca $paca): static { $this->paca = $paca; return $this; }

    public function getWarehouse(): Warehouse { return $this->warehouse; }
    public function setWarehouse(Warehouse $warehouse): static { $this->warehouse = $warehouse; return $this; }

    public function getWarehouseBin(): ?WarehouseBin { return $this->warehouseBin; }
    public function setWarehouseBin(?WarehouseBin $warehouseBin): static { $this->warehouseBin = $warehouseBin; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
