<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateBranchRequest;
use App\DTO\Request\UpdateBranchRequest;
use App\DTO\Response\BranchResponse;
use App\Entity\Branch;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\BranchRepository;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class BranchService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BranchRepository $branchRepository,
        private CompanyRepository $companyRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination, ?int $companyId = null): PaginatedResponse
    {
        $qb = $this->branchRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            companyId: $companyId,
        );

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (Branch $branch) => new BranchResponse($branch),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): BranchResponse
    {
        $branch = $this->branchRepository->find($id);
        if ($branch === null) {
            throw new NotFoundHttpException(sprintf('Sucursal con ID %d no encontrada.', $id));
        }
        return new BranchResponse($branch);
    }

    public function create(CreateBranchRequest $request): BranchResponse
    {
        $company = $this->companyRepository->find($request->companyId);
        if ($company === null) {
            throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $request->companyId));
        }

        $branch = new Branch();
        $branch->setCompany($company);
        $branch->setName($request->name);
        $branch->setAbbreviations($request->abbreviations);
        $branch->setAddress($request->address);
        $branch->setPhone($request->phone);
        $branch->setSchedule($request->schedule);
        $branch->setImage($request->image);

        $this->em->persist($branch);
        $this->em->flush();

        return new BranchResponse($branch);
    }

    public function update(int $id, UpdateBranchRequest $request): BranchResponse
    {
        $branch = $this->branchRepository->find($id);
        if ($branch === null) {
            throw new NotFoundHttpException(sprintf('Sucursal con ID %d no encontrada.', $id));
        }

        if ($request->name !== null) $branch->setName($request->name);
        if ($request->abbreviations !== null) $branch->setAbbreviations($request->abbreviations);
        if ($request->address !== null) $branch->setAddress($request->address);
        if ($request->phone !== null) $branch->setPhone($request->phone);
        if ($request->schedule !== null) $branch->setSchedule($request->schedule);
        if ($request->image !== null) $branch->setImage($request->image);
        if ($request->active !== null) $branch->setActive($request->active);

        $this->em->flush();

        return new BranchResponse($branch);
    }

    public function delete(int $id): void
    {
        $branch = $this->branchRepository->find($id);
        if ($branch === null) {
            throw new NotFoundHttpException(sprintf('Sucursal con ID %d no encontrada.', $id));
        }
        $this->em->remove($branch);
        $this->em->flush();
    }
}
