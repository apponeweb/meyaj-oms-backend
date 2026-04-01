<?php

declare(strict_types=1);

namespace App\Pagination;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PaginationRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 1000)]
        public int $limit = 10,

        public ?string $sort = null,

        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $order = 'asc',

        public ?string $search = null,
        public ?int $roleId = null,
        public ?string $active = null,
        public ?int $companyId = null,
        public ?int $branchId = null,
        public ?int $departmentId = null,
        public ?string $warehouseType = null,
        public ?int $warehouseId = null,
        public ?int $pacaId = null,
        public ?string $movementType = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $direction = null,
        public ?int $reasonId = null,
        public ?int $supplierId = null,
        public ?string $status = null,
        public ?string $channel = null,
        public ?string $paymentStatus = null,
        public ?int $customerId = null,
        public ?int $sellerId = null,
    ) {
    }
}
