<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateDepartmentRequest;
use App\DTO\Request\UpdateDepartmentRequest;
use App\DTO\Response\DepartmentResponse;
use App\Entity\Department;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\CompanyRepository;
use App\Repository\DepartmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DepartmentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DepartmentRepository $departmentRepository,
        private CompanyRepository $companyRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination, ?int $companyId = null): PaginatedResponse
    {
        $qb = $this->departmentRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            companyId: $companyId,
        );

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (Department $department) => new DepartmentResponse($department),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): DepartmentResponse
    {
        $department = $this->departmentRepository->find($id);
        if ($department === null) {
            throw new NotFoundHttpException(sprintf('Departamento con ID %d no encontrado.', $id));
        }
        return new DepartmentResponse($department);
    }

    public function create(CreateDepartmentRequest $request): DepartmentResponse
    {
        $company = $this->companyRepository->find($request->companyId);
        if ($company === null) {
            throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $request->companyId));
        }

        $department = new Department();
        $department->setCompany($company);
        $department->setName($request->name);
        $department->setAcronym($request->acronym);
        $department->setDescription($request->description);

        $this->em->persist($department);
        $this->em->flush();

        return new DepartmentResponse($department);
    }

    public function update(int $id, UpdateDepartmentRequest $request): DepartmentResponse
    {
        $department = $this->departmentRepository->find($id);
        if ($department === null) {
            throw new NotFoundHttpException(sprintf('Departamento con ID %d no encontrado.', $id));
        }

        if ($request->name !== null) $department->setName($request->name);
        if ($request->acronym !== null) $department->setAcronym($request->acronym);
        if ($request->description !== null) $department->setDescription($request->description);
        if ($request->active !== null) $department->setActive($request->active);

        $this->em->flush();

        return new DepartmentResponse($department);
    }

    public function delete(int $id): void
    {
        $department = $this->departmentRepository->find($id);
        if ($department === null) {
            throw new NotFoundHttpException(sprintf('Departamento con ID %d no encontrado.', $id));
        }
        $this->em->remove($department);
        $this->em->flush();
    }
}
