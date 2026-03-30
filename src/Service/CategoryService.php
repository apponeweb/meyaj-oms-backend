<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateCategoryRequest;
use App\DTO\Request\UpdateCategoryRequest;
use App\DTO\Response\CategoryResponse;
use App\Entity\Category;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CategoryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->categoryRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (Category $category) => new CategoryResponse($category),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): CategoryResponse
    {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            throw new NotFoundHttpException(sprintf('Categoría con ID %d no encontrada.', $id));
        }

        return new CategoryResponse($category);
    }

    public function create(CreateCategoryRequest $request): CategoryResponse
    {
        $category = new Category();
        $category->setName($request->name);
        $category->setAcronym($request->acronym);
        $category->setDescription($request->description);

        $this->em->persist($category);
        $this->em->flush();

        return new CategoryResponse($category);
    }

    public function update(int $id, UpdateCategoryRequest $request): CategoryResponse
    {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            throw new NotFoundHttpException(sprintf('Categoría con ID %d no encontrada.', $id));
        }

        if ($request->name !== null) {
            $category->setName($request->name);
        }

        if ($request->acronym !== null) {
            $category->setAcronym($request->acronym);
        }

        if ($request->description !== null) {
            $category->setDescription($request->description);
        }

        $this->em->flush();

        return new CategoryResponse($category);
    }

    public function delete(int $id): void
    {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            throw new NotFoundHttpException(sprintf('Categoría con ID %d no encontrada.', $id));
        }

        $this->em->remove($category);
        $this->em->flush();
    }
}
