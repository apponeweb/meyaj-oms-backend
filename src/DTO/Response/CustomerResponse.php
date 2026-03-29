<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Customer;

final readonly class CustomerResponse
{
    public int $id;
    public string $name;
    public ?string $email;
    public ?string $phone;
    public ?string $address;
    public ?string $taxId;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Customer $customer)
    {
        $this->id = $customer->getId();
        $this->name = $customer->getName();
        $this->email = $customer->getEmail();
        $this->phone = $customer->getPhone();
        $this->address = $customer->getAddress();
        $this->taxId = $customer->getTaxId();
        $this->active = $customer->isActive();
        $this->createdAt = $customer->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $customer->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
