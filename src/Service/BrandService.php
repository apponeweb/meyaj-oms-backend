<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateBrandRequest;
use App\DTO\Request\UpdateBrandRequest;
use App\DTO\Response\BrandResponse;
use App\Entity\Brand;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\BrandRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class BrandService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BrandRepository $brandRepository,
        private Paginator $paginator,
    ) {}

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->brandRepository->createPaginatedQueryBuilder(search: $pagination->search);
        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);
        return new PaginatedResponse(
            data: array_map(static fn (Brand $b) => new BrandResponse($b), $result->data),
            meta: $result->meta,
        );
    }

    public function show(int $id): BrandResponse
    {
        $b = $this->brandRepository->find($id);
        if ($b === null) throw new NotFoundHttpException(sprintf('Marca con ID %d no encontrada.', $id));
        return new BrandResponse($b);
    }

    public function create(CreateBrandRequest $request): BrandResponse
    {
        $b = new Brand();
        $b->setName($request->name);
        $b->setDescription($request->description);
        $this->em->persist($b);
        $this->em->flush();
        return new BrandResponse($b);
    }

    public function update(int $id, UpdateBrandRequest $request): BrandResponse
    {
        $b = $this->brandRepository->find($id);
        if ($b === null) throw new NotFoundHttpException(sprintf('Marca con ID %d no encontrada.', $id));
        if ($request->name !== null) $b->setName($request->name);
        if ($request->description !== null) $b->setDescription($request->description);
        if ($request->active !== null) $b->setActive($request->active);
        $this->em->flush();
        return new BrandResponse($b);
    }

    public function delete(int $id): void
    {
        $b = $this->brandRepository->find($id);
        if ($b === null) throw new NotFoundHttpException(sprintf('Marca con ID %d no encontrada.', $id));
        $this->em->remove($b);
        $this->em->flush();
    }
}
