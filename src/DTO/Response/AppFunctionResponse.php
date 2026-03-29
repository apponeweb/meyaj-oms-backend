<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\AppFunction;

final readonly class AppFunctionResponse
{
    public int $id;
    public string $code;
    public string $name;
    public int $moduleId;
    public string $moduleName;
    public int $displayOrder;
    public bool $active;
    public string $createdAt;

    public function __construct(AppFunction $function)
    {
        $this->id = $function->getId();
        $this->code = $function->getCode();
        $this->name = $function->getName();
        $this->moduleId = $function->getAppModule()->getId();
        $this->moduleName = $function->getAppModule()->getName();
        $this->displayOrder = $function->getDisplayOrder();
        $this->active = $function->isActive();
        $this->createdAt = $function->getCreatedAt()->format(\DateTimeInterface::ATOM);
    }
}
