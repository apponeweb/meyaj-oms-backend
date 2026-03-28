<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Product;

final readonly class ProductResponse
{
    public int $id;
    public string $name;
    public ?string $description;
    public string $price;
    public int $stock;
    public ?CategoryResponse $category;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Product $product)
    {
        $this->id = $product->getId();
        $this->name = $product->getName();
        $this->description = $product->getDescription();
        $this->price = $product->getPrice();
        $this->stock = $product->getStock();
        $this->category = $product->getCategory() !== null
            ? new CategoryResponse($product->getCategory())
            : null;
        $this->active = $product->isActive();
        $this->createdAt = $product->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $product->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
