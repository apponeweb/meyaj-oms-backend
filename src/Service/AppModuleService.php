<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateAppModuleRequest;
use App\DTO\Request\UpdateAppModuleRequest;
use App\DTO\Response\AppModuleResponse;
use App\Entity\AppModule;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\AppModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AppModuleService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AppModuleRepository $appModuleRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->appModuleRepository->createQueryBuilder('m');

        if ($pagination->search) {
            $qb->andWhere('m.name LIKE :s OR m.code LIKE :s')
                ->setParameter('s', "%{$pagination->search}%");
        }

        if ($pagination->sort === null) {
            $qb->orderBy('m.displayOrder', 'ASC');
        }

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (AppModule $m) => new AppModuleResponse($m),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function create(CreateAppModuleRequest $request): AppModuleResponse
    {
        $module = new AppModule();
        $module->setCode($request->code);
        $module->setName($request->name);
        $module->setIcon($request->icon);
        $module->setDisplayOrder($request->displayOrder);

        $this->em->persist($module);
        $this->em->flush();

        return new AppModuleResponse($module);
    }

    public function update(int $id, UpdateAppModuleRequest $request): AppModuleResponse
    {
        $module = $this->appModuleRepository->find($id);
        if ($module === null) {
            throw new NotFoundHttpException(sprintf('Módulo con ID %d no encontrado.', $id));
        }

        if ($request->code !== null) $module->setCode($request->code);
        if ($request->name !== null) $module->setName($request->name);
        if ($request->icon !== null) $module->setIcon($request->icon);
        if ($request->displayOrder !== null) $module->setDisplayOrder($request->displayOrder);
        if ($request->active !== null) $module->setActive($request->active);

        $this->em->flush();

        return new AppModuleResponse($module);
    }

    public function delete(int $id): void
    {
        $module = $this->appModuleRepository->find($id);
        if ($module === null) {
            throw new NotFoundHttpException(sprintf('Módulo con ID %d no encontrado.', $id));
        }

        $this->em->remove($module);
        $this->em->flush();
    }
}
