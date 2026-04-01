<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\InventoryCountDetail;

final readonly class InventoryCountDetailResponse
{
    public int $id;
    public array $paca;
    public int $systemQty;
    public ?int $countedQty;
    public ?int $difference;
    public string $status;
    public ?string $notes;
    public ?string $countedAt;

    public function __construct(InventoryCountDetail $d)
    {
        $this->id = $d->getId();
        $this->paca = [
            'id' => $d->getPaca()->getId(),
            'code' => $d->getPaca()->getCode(),
            'name' => $d->getPaca()->getName(),
        ];
        $this->systemQty = $d->getSystemQty();
        $this->countedQty = $d->getCountedQty();
        $this->difference = $d->getDifference();
        $this->status = $d->getStatus();
        $this->notes = $d->getNotes();
        $this->countedAt = $d->getCountedAt()?->format(\DateTimeInterface::ATOM);
    }
}
