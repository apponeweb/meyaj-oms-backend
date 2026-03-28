<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateCompanyRequest;
use App\DTO\Request\UpdateCompanyRequest;
use App\DTO\Response\CompanyResponse;
use App\Entity\Company;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CompanyService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CompanyRepository $companyRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->companyRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (Company $company) => new CompanyResponse($company),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): CompanyResponse
    {
        $company = $this->companyRepository->find($id);
        if ($company === null) {
            throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $id));
        }
        return new CompanyResponse($company);
    }

    public function create(CreateCompanyRequest $request): CompanyResponse
    {
        $company = new Company();
        $company->setName($request->name);
        $company->setTradeName($request->tradeName);
        $company->setTaxId($request->taxId);
        $company->setAddress($request->address);
        $company->setPhone($request->phone);
        $company->setEmail($request->email);

        $this->em->persist($company);
        $this->em->flush();

        return new CompanyResponse($company);
    }

    public function update(int $id, UpdateCompanyRequest $request): CompanyResponse
    {
        $company = $this->companyRepository->find($id);
        if ($company === null) {
            throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $id));
        }

        if ($request->name !== null) $company->setName($request->name);
        if ($request->tradeName !== null) $company->setTradeName($request->tradeName);
        if ($request->taxId !== null) $company->setTaxId($request->taxId);
        if ($request->address !== null) $company->setAddress($request->address);
        if ($request->phone !== null) $company->setPhone($request->phone);
        if ($request->email !== null) $company->setEmail($request->email);
        if ($request->active !== null) $company->setActive($request->active);

        $this->em->flush();

        return new CompanyResponse($company);
    }

    public function delete(int $id): void
    {
        $company = $this->companyRepository->find($id);
        if ($company === null) {
            throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $id));
        }
        $this->em->remove($company);
        $this->em->flush();
    }
}
