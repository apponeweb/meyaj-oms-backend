<?php

declare(strict_types=1);

namespace App\DTO\Request;

final readonly class ShipShipmentOrderRequest
{
    public function __construct(
        public ?string $trackingNumber = null,
        public ?string $carrier = null,
        public ?string $notes = null,
    ) {}
}
