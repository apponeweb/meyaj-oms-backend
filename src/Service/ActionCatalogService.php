<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateActionCatalogRequest;
use App\DTO\Request\UpdateActionCatalogRequest;
use App\DTO\Response\ActionCatalogResponse;
use App\Entity\ActionCatalog;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\ActionCatalogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ActionCatalogService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActionCatalogRepository $actionCatalogRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->actionCatalogRepository->createQueryBuilder('a');

        if ($pagination->search) {
            $qb->andWhere('a.name LIKE :s OR a.code LIKE :s')
                ->setParameter('s', "%{$pagination->search}%");
        }

        if ($pagination->sort === null) {
            $qb->orderBy('a.id', 'ASC');
        }

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (ActionCatalog $a) => new ActionCatalogResponse($a),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function create(CreateActionCatalogRequest $request): ActionCatalogResponse
    {
        $action = new ActionCatalog();
        $action->setCode($request->code);
        $action->setName($request->name);

        $this->em->persist($action);
        $this->em->flush();

        return new ActionCatalogResponse($action);
    }

    public function update(int $id, UpdateActionCatalogRequest $request): ActionCatalogResponse
    {
        $action = $this->actionCatalogRepository->find($id);
        if ($action === null) {
            throw new NotFoundHttpException(sprintf('Acción con ID %d no encontrada.', $id));
        }

        if ($request->code !== null) $action->setCode($request->code);
        if ($request->name !== null) $action->setName($request->name);

        $this->em->flush();

        return new ActionCatalogResponse($action);
    }

    public function delete(int $id): void
    {
        $action = $this->actionCatalogRepository->find($id);
        if ($action === null) {
            throw new NotFoundHttpException(sprintf('Acción con ID %d no encontrada.', $id));
        }

        $this->em->remove($action);
        $this->em->flush();
    }
}
