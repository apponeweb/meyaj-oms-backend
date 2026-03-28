<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateProductRequest;
use App\DTO\Request\UpdateProductRequest;
use App\DTO\Response\ProductResponse;
use App\Entity\Product;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ProductService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(
        PaginationRequest $pagination,
        ?int $categoryId = null,
        ?bool $active = null,
        ?string $minPrice = null,
        ?string $maxPrice = null,
    ): PaginatedResponse {
        $qb = $this->productRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            categoryId: $categoryId,
            active: $active,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
        );

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (Product $product) => new ProductResponse($product),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): ProductResponse
    {
        $product = $this->productRepository->find($id);

        if ($product === null) {
            throw new NotFoundHttpException(sprintf('Producto con ID %d no encontrado.', $id));
        }

        return new ProductResponse($product);
    }

    public function create(CreateProductRequest $request): ProductResponse
    {
        $product = new Product();
        $product->setName($request->name);
        $product->setDescription($request->description);
        $product->setPrice($request->price);
        $product->setStock($request->stock);
        $product->setActive($request->active);

        if ($request->categoryId !== null) {
            $category = $this->categoryRepository->find($request->categoryId);
            if ($category === null) {
                throw new NotFoundHttpException(sprintf('Categoría con ID %d no encontrada.', $request->categoryId));
            }
            $product->setCategory($category);
        }

        $this->em->persist($product);
        $this->em->flush();

        return new ProductResponse($product);
    }

    public function update(int $id, UpdateProductRequest $request): ProductResponse
    {
        $product = $this->productRepository->find($id);

        if ($product === null) {
            throw new NotFoundHttpException(sprintf('Producto con ID %d no encontrado.', $id));
        }

        if ($request->name !== null) {
            $product->setName($request->name);
        }

        if ($request->description !== null) {
            $product->setDescription($request->description);
        }

        if ($request->price !== null) {
            $product->setPrice($request->price);
        }

        if ($request->stock !== null) {
            $product->setStock($request->stock);
        }

        if ($request->active !== null) {
            $product->setActive($request->active);
        }

        if ($request->categoryId !== null) {
            $category = $this->categoryRepository->find($request->categoryId);
            if ($category === null) {
                throw new NotFoundHttpException(sprintf('Categoría con ID %d no encontrada.', $request->categoryId));
            }
            $product->setCategory($category);
        }

        $this->em->flush();

        return new ProductResponse($product);
    }

    public function delete(int $id): void
    {
        $product = $this->productRepository->find($id);

        if ($product === null) {
            throw new NotFoundHttpException(sprintf('Producto con ID %d no encontrado.', $id));
        }

        $this->em->remove($product);
        $this->em->flush();
    }
}
