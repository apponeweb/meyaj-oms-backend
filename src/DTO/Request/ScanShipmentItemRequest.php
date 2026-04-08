<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ScanShipmentItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $serial,
    ) {}
}
