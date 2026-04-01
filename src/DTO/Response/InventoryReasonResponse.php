<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\InventoryReason;

final readonly class InventoryReasonResponse
{
    public int $id;
    public string $code;
    public string $name;
    public string $direction;
    public bool $requiresReference;
    public bool $isActive;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(InventoryReason $reason)
    {
        $this->id = $reason->getId();
        $this->code = $reason->getCode();
        $this->name = $reason->getName();
        $this->direction = $reason->getDirection();
        $this->requiresReference = $reason->isRequiresReference();
        $this->isActive = $reason->isActive();
        $this->createdAt = $reason->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $reason->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
