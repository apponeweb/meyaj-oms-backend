<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\LabelCatalog;
use App\Entity\Supplier;
use App\Entity\SupplierBrand;

final readonly class SupplierResponse
{
    public int $id;
    public string $name;
    public ?array $contacts;
    public ?string $address;
    public ?string $country;
    public ?string $taxId;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;
    public array $brands;
    public array $tags;

    public function __construct(Supplier $supplier)
    {
        $this->id = $supplier->getId();
        $this->name = $supplier->getName();
        $this->contacts = $supplier->getContacts();
        $this->address = $supplier->getAddress();
        $this->country = $supplier->getCountry();
        $this->taxId = $supplier->getTaxId();
        $this->active = $supplier->isActive();
        $this->createdAt = $supplier->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $supplier->getUpdatedAt()->format(\DateTimeInterface::ATOM);
        
        $this->brands = array_values($supplier->getSupplierBrands()->map(function (SupplierBrand $sb) {
            $brand = $sb->getBrand();
            return [
                'id' => $brand->getId(),
                'name' => $brand->getName(),
            ];
        })->toArray());

        $this->tags = array_values($supplier->getTags()->map(function (LabelCatalog $label) {
            return [
                'id' => $label->getId(),
                'name' => $label->getName(),
            ];
        })->toArray());
    }
}
