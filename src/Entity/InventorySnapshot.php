<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InventorySnapshotRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: InventorySnapshotRepository::class)]
#[ORM\Table(name: 'inventory_snapshot')]
#[ORM\Index(columns: ['company_id', 'warehouse_id', 'snapshot_date'], name: 'idx_snapshot_composite')]
#[UniqueEntity(fields: ['company', 'warehouse', 'snapshotDate'], message: 'Ya existe un snapshot para esta bodega en esta fecha')]
class InventorySnapshot
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

    #[ORM\Column(type: 'date')]
    private \DateTimeImmutable $snapshotDate;

    #[ORM\Column]
    private int $totalPacas = 0;

    #[ORM\Column]
    private int $totalStock = 0;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $totalValuePurchase = '0.00';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $totalValueSelling = '0.00';

    #[ORM\Column]
    private int $movementsIn = 0;

    #[ORM\Column]
    private int $movementsOut = 0;

    #[ORM\Column]
    private int $lowStockCount = 0;

    #[ORM\Column]
    private int $outOfStockCount = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $dataJson = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): static { $this->company = $company; return $this; }

    public function getWarehouse(): Warehouse { return $this->warehouse; }
    public function setWarehouse(Warehouse $warehouse): static { $this->warehouse = $warehouse; return $this; }

    public function getSnapshotDate(): \DateTimeImmutable { return $this->snapshotDate; }
    public function setSnapshotDate(\DateTimeImmutable $snapshotDate): static { $this->snapshotDate = $snapshotDate; return $this; }

    public function getTotalPacas(): int { return $this->totalPacas; }
    public function setTotalPacas(int $totalPacas): static { $this->totalPacas = $totalPacas; return $this; }

    public function getTotalStock(): int { return $this->totalStock; }
    public function setTotalStock(int $totalStock): static { $this->totalStock = $totalStock; return $this; }

    public function getTotalValuePurchase(): string { return $this->totalValuePurchase; }
    public function setTotalValuePurchase(string $totalValuePurchase): static { $this->totalValuePurchase = $totalValuePurchase; return $this; }

    public function getTotalValueSelling(): string { return $this->totalValueSelling; }
    public function setTotalValueSelling(string $totalValueSelling): static { $this->totalValueSelling = $totalValueSelling; return $this; }

    public function getMovementsIn(): int { return $this->movementsIn; }
    public function setMovementsIn(int $movementsIn): static { $this->movementsIn = $movementsIn; return $this; }

    public function getMovementsOut(): int { return $this->movementsOut; }
    public function setMovementsOut(int $movementsOut): static { $this->movementsOut = $movementsOut; return $this; }

    public function getLowStockCount(): int { return $this->lowStockCount; }
    public function setLowStockCount(int $lowStockCount): static { $this->lowStockCount = $lowStockCount; return $this; }

    public function getOutOfStockCount(): int { return $this->outOfStockCount; }
    public function setOutOfStockCount(int $outOfStockCount): static { $this->outOfStockCount = $outOfStockCount; return $this; }

    public function getDataJson(): ?array { return $this->dataJson; }
    public function setDataJson(?array $dataJson): static { $this->dataJson = $dataJson; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
