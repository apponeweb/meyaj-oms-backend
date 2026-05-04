<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PartialReturnSalesOrderRequest
{
    /**
     * @param list<int> $unitIds
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\Type('integer'),
            new Assert\Positive(),
        ])]
        public array $unitIds,
        public ?string $notes = null,
    ) {}
}
