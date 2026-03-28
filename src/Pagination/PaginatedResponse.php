<?php

declare(strict_types=1);

namespace App\Pagination;

final readonly class PaginatedResponse
{
    public function __construct(
        public array $data,
        public PaginationMeta $meta,
    ) {
    }
}
