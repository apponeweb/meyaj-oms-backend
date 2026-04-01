<?php

declare(strict_types=1);

namespace App\DTO\Request;

final readonly class UpdateInventoryCountRequest
{
    public function __construct(
        public ?string $status = null,
        public ?string $notes = null,
    ) {
    }
}
