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
use App\Repository\BranchRepository;
use App\Repository\DepartmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DepartmentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DepartmentRepository $departmentRepository,
        private BranchRepository $branchRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination, ?int $branchId = null): PaginatedResponse
    {
        $qb = $this->departmentRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            branchId: $branchId,
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
        $branch = $this->branchRepository->find($request->branchId);
        if ($branch === null) {
            throw new NotFoundHttpException(sprintf('Sucursal con ID %d no encontrada.', $request->branchId));
        }

        $department = new Department();
        $department->setBranch($branch);
        $department->setName($request->name);
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
