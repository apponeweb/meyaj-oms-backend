<?php

declare(strict_types=1);

namespace App\Pagination;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

final class Paginator
{
    public function paginate(
        QueryBuilder $queryBuilder,
        PaginationRequest $request,
        bool $fetchJoinCollection = true,
    ): PaginatedResponse {
        $queryBuilder
            ->setFirstResult(($request->page - 1) * $request->limit)
            ->setMaxResults($request->limit);

        if ($request->sort !== null) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->addOrderBy(
                sprintf('%s.%s', $rootAlias, $request->sort),
                $request->order,
            );
        }

        $doctrinePaginator = new DoctrinePaginator($queryBuilder, $fetchJoinCollection);
        $doctrinePaginator->setUseOutputWalkers(false);

        $total = $doctrinePaginator->count();

        return new PaginatedResponse(
            data: iterator_to_array($doctrinePaginator->getIterator()),
            meta: new PaginationMeta(
                total: $total,
                page: $request->page,
                limit: $request->limit,
            ),
        );
    }
}
