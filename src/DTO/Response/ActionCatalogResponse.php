<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\ActionCatalog;

final readonly class ActionCatalogResponse
{
    public int $id;
    public string $code;
    public string $name;

    public function __construct(ActionCatalog $action)
    {
        $this->id = $action->getId();
        $this->code = $action->getCode();
        $this->name = $action->getName();
    }
}
