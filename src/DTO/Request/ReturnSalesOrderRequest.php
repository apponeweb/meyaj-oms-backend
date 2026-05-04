<?php

declare(strict_types=1);

namespace App\DTO\Request;

final readonly class ReturnSalesOrderRequest
{
    public function __construct(
        public ?string $notes = null,
    ) {}
}
