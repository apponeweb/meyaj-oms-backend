<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateSupplierRequest;
use App\DTO\Request\UpdateSupplierRequest;
use App\DTO\Response\SupplierResponse;
use App\Entity\Supplier;
use App\Entity\SupplierBrand;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\BrandRepository;
use App\Repository\LabelCatalogRepository;
use App\Repository\SupplierBrandRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class SupplierService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SupplierRepository $supplierRepository,
        private BrandRepository $brandRepository,
        private SupplierBrandRepository $supplierBrandRepository,
        private LabelCatalogRepository $labelCatalogRepository,
        private Paginator $paginator,
    ) {}

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->supplierRepository->createPaginatedQueryBuilder(search: $pagination->search);
        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);
        return new PaginatedResponse(
            data: array_map(static fn (Supplier $s) => new SupplierResponse($s), $result->data),
            meta: $result->meta,
        );
    }

    public function show(int $id): SupplierResponse
    {
        $s = $this->supplierRepository->find($id);
        if ($s === null) throw new NotFoundHttpException(sprintf('Proveedor con ID %d no encontrado.', $id));
        return new SupplierResponse($s);
    }

    public function create(CreateSupplierRequest $request): SupplierResponse
    {
        $s = new Supplier();
        $s->setName($request->name);
        $s->setContacts($request->contacts);
        $s->setAddress($request->address);
        $s->setCountry($request->country);
        $s->setTaxId($request->taxId);
        $this->em->persist($s);
        $this->em->flush();
        return new SupplierResponse($s);
    }

    public function update(int $id, UpdateSupplierRequest $request): SupplierResponse
    {
        $s = $this->supplierRepository->find($id);
        if ($s === null) throw new NotFoundHttpException(sprintf('Proveedor con ID %d no encontrado.', $id));
        if ($request->name !== null) $s->setName($request->name);
        if ($request->contacts !== null) $s->setContacts($request->contacts);
        if ($request->address !== null) $s->setAddress($request->address);
        if ($request->country !== null) $s->setCountry($request->country);
        if ($request->taxId !== null) $s->setTaxId($request->taxId);
        if ($request->active !== null) $s->setActive($request->active);
        $this->em->flush();
        return new SupplierResponse($s);
    }

    public function delete(int $id): void
    {
        $s = $this->supplierRepository->find($id);
        if ($s === null) throw new NotFoundHttpException(sprintf('Proveedor con ID %d no encontrado.', $id));
        $this->em->remove($s);
        $this->em->flush();
    }

    public function assignBrands(int $supplierId, array $brandIds): SupplierResponse
    {
        $supplier = $this->supplierRepository->find($supplierId);
        if ($supplier === null) {
            throw new NotFoundHttpException(sprintf('Proveedor con ID %d no encontrado.', $supplierId));
        }

        // Clear existing supplier brands
        foreach ($supplier->getSupplierBrands() as $sb) {
            $supplier->getSupplierBrands()->removeElement($sb);
            $this->em->remove($sb);
        }
        $this->em->flush();

        // Add new supplier brands
        foreach ($brandIds as $brandId) {
            $brand = $this->brandRepository->find($brandId);
            if ($brand !== null) {
                $sb = new SupplierBrand();
                $sb->setSupplier($supplier);
                $sb->setBrand($brand);
                $this->em->persist($sb);
                $supplier->getSupplierBrands()->add($sb);
            }
        }

        $this->em->flush();
        return new SupplierResponse($supplier);
    }

    public function assignTags(int $supplierId, array $tagIds): SupplierResponse
    {
        $supplier = $this->supplierRepository->find($supplierId);
        if ($supplier === null) {
            throw new NotFoundHttpException(sprintf('Proveedor con ID %d no encontrado.', $supplierId));
        }

        // Clear existing tags
        foreach ($supplier->getTags() as $tag) {
            $supplier->removeTag($tag);
        }

        // Add new tags
        foreach ($tagIds as $tagId) {
            $label = $this->labelCatalogRepository->find($tagId);
            if ($label !== null) {
                $supplier->addTag($label);
            }
        }

        $this->em->flush();
        return new SupplierResponse($supplier);
    }
}
