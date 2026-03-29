<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\AppModule;

final readonly class AppModuleResponse
{
    public int $id;
    public string $code;
    public string $name;
    public string $icon;
    public int $displayOrder;
    public bool $active;
    public string $createdAt;

    public function __construct(AppModule $module)
    {
        $this->id = $module->getId();
        $this->code = $module->getCode();
        $this->name = $module->getName();
        $this->icon = $module->getIcon();
        $this->displayOrder = $module->getDisplayOrder();
        $this->active = $module->isActive();
        $this->createdAt = $module->getCreatedAt()->format(\DateTimeInterface::ATOM);
    }
}
