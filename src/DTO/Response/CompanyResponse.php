<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Company;

final readonly class CompanyResponse
{
    public int $id;
    public string $name;
    public ?string $acronym;
    public ?string $tradeName;
    public ?string $legalName;
    public ?string $tagline;
    public ?string $description;
    public ?string $taxId;
    public ?array $address;
    public ?string $phone;
    public ?string $email;
    public ?string $url;
    public ?string $image;
    public ?array $socialNetworks;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Company $company)
    {
        $this->id = $company->getId();
        $this->name = $company->getName();
        $this->acronym = $company->getAcronym();
        $this->tradeName = $company->getTradeName();
        $this->legalName = $company->getLegalName();
        $this->tagline = $company->getTagline();
        $this->description = $company->getDescription();
        $this->taxId = $company->getTaxId();
        $this->address = $company->getAddress();
        $this->phone = $company->getPhone();
        $this->email = $company->getEmail();
        $this->url = $company->getUrl();
        $this->image = $company->getImage();
        $this->socialNetworks = $company->getSocialNetworks();
        $this->active = $company->isActive();
        $this->createdAt = $company->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $company->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
