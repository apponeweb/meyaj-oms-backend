<?php

declare(strict_types=1);

namespace App\Pagination;

final readonly class PaginationMeta
{
    public int $totalPages;

    public function __construct(
        public int $total,
        public int $page,
        public int $limit,
    ) {
        $this->totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;
    }
}
