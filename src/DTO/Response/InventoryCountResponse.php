<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\InventoryCount;

final readonly class InventoryCountResponse
{
    public int $id;
    public array $company;
    public array $warehouse;
    public array $user;
    public string $folio;
    public string $status;
    public string $countDate;
    public ?string $notes;
    public int $totalItems;
    public int $discrepancies;
    public int $detailsCount;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(InventoryCount $c)
    {
        $this->id = $c->getId();
        $this->company = [
            'id' => $c->getCompany()->getId(),
            'name' => $c->getCompany()->getName(),
        ];
        $this->warehouse = [
            'id' => $c->getWarehouse()->getId(),
            'code' => $c->getWarehouse()->getCode(),
            'name' => $c->getWarehouse()->getName(),
        ];
        $this->user = [
            'id' => $c->getUser()->getId(),
            'name' => $c->getUser()->getName(),
        ];
        $this->folio = $c->getFolio();
        $this->status = $c->getStatus();
        $this->countDate = $c->getCountDate()->format('Y-m-d');
        $this->notes = $c->getNotes();
        $this->totalItems = $c->getTotalItems();
        $this->discrepancies = $c->getDiscrepancies();
        $this->detailsCount = $c->getDetails()->count();
        $this->createdAt = $c->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $c->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
