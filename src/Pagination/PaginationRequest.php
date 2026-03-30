<?php

declare(strict_types=1);

namespace App\Pagination;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PaginationRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        public ?string $sort = null,

        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $order = 'asc',

        public ?string $search = null,
        public ?int $roleId = null,
        public ?string $active = null,
    ) {
    }
}
