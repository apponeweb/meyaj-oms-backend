<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateAppFunctionRequest;
use App\DTO\Request\UpdateAppFunctionRequest;
use App\DTO\Response\AppFunctionResponse;
use App\Entity\AppFunction;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\AppFunctionRepository;
use App\Repository\AppModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AppFunctionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AppFunctionRepository $appFunctionRepository,
        private AppModuleRepository $appModuleRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination, ?int $moduleId = null): PaginatedResponse
    {
        $qb = $this->appFunctionRepository->createQueryBuilder('f')
            ->join('f.appModule', 'm')
            ->addSelect('m');

        if ($moduleId) {
            $qb->andWhere('f.appModule = :mid')->setParameter('mid', $moduleId);
        }

        if ($pagination->search) {
            $qb->andWhere('f.name LIKE :s OR f.code LIKE :s')
                ->setParameter('s', "%{$pagination->search}%");
        }

        if ($pagination->sort === null) {
            $qb->orderBy('m.displayOrder', 'ASC')
                ->addOrderBy('f.displayOrder', 'ASC');
        }

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (AppFunction $f) => new AppFunctionResponse($f),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function create(CreateAppFunctionRequest $request): AppFunctionResponse
    {
        $module = $this->appModuleRepository->find($request->moduleId);
        if ($module === null) {
            throw new NotFoundHttpException(sprintf('Módulo con ID %d no encontrado.', $request->moduleId));
        }

        $function = new AppFunction();
        $function->setAppModule($module);
        $function->setCode($request->code);
        $function->setName($request->name);
        $function->setDisplayOrder($request->displayOrder);

        $this->em->persist($function);
        $this->em->flush();

        return new AppFunctionResponse($function);
    }

    public function update(int $id, UpdateAppFunctionRequest $request): AppFunctionResponse
    {
        $function = $this->appFunctionRepository->find($id);
        if ($function === null) {
            throw new NotFoundHttpException(sprintf('Funcionalidad con ID %d no encontrada.', $id));
        }

        if ($request->code !== null) $function->setCode($request->code);
        if ($request->name !== null) $function->setName($request->name);
        if ($request->displayOrder !== null) $function->setDisplayOrder($request->displayOrder);
        if ($request->active !== null) $function->setActive($request->active);
        if ($request->moduleId !== null) {
            $module = $this->appModuleRepository->find($request->moduleId);
            if ($module === null) {
                throw new NotFoundHttpException(sprintf('Módulo con ID %d no encontrado.', $request->moduleId));
            }
            $function->setAppModule($module);
        }

        $this->em->flush();

        return new AppFunctionResponse($function);
    }

    public function delete(int $id): void
    {
        $function = $this->appFunctionRepository->find($id);
        if ($function === null) {
            throw new NotFoundHttpException(sprintf('Funcionalidad con ID %d no encontrada.', $id));
        }

        $this->em->remove($function);
        $this->em->flush();
    }
}
