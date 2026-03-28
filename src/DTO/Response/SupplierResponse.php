<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Supplier;

final readonly class SupplierResponse
{
    public int $id;
    public string $name;
    public ?string $contactName;
    public ?string $email;
    public ?string $phone;
    public ?string $address;
    public ?string $country;
    public ?string $taxId;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Supplier $s)
    {
        $this->id = $s->getId();
        $this->name = $s->getName();
        $this->contactName = $s->getContactName();
        $this->email = $s->getEmail();
        $this->phone = $s->getPhone();
        $this->address = $s->getAddress();
        $this->country = $s->getCountry();
        $this->taxId = $s->getTaxId();
        $this->active = $s->isActive();
        $this->createdAt = $s->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $s->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
