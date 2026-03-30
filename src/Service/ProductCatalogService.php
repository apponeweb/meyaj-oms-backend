<?php

declare(strict_types=1);

namespace App\Service;

use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ProductCatalogService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Paginator $paginator,
    ) {}

    public function list(string $entityClass, PaginationRequest $pagination): PaginatedResponse
    {
        $repo = $this->em->getRepository($entityClass);
        /** @var QueryBuilder $qb */
        $qb = $repo->createPaginatedQueryBuilder($pagination->search);
        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(fn ($e) => $this->toArray($e), $result->data),
            meta: $result->meta,
        );
    }

    public function show(string $entityClass, int $id): array
    {
        $entity = $this->em->getRepository($entityClass)->find($id);
        if ($entity === null) throw new NotFoundHttpException('Registro no encontrado.');
        return $this->toArray($entity);
    }

    public function create(string $entityClass, string $name, ?string $acronym = null, ?string $description = null): array
    {
        $entity = new $entityClass();
        $entity->setName($name);
        $entity->setAcronym($acronym);
        $entity->setDescription($description);
        $this->em->persist($entity);
        $this->em->flush();
        return $this->toArray($entity);
    }

    public function update(string $entityClass, int $id, ?string $name, ?string $acronym, ?string $description, ?bool $active): array
    {
        $entity = $this->em->getRepository($entityClass)->find($id);
        if ($entity === null) throw new NotFoundHttpException('Registro no encontrado.');
        if ($name !== null) $entity->setName($name);
        if ($acronym !== null) $entity->setAcronym($acronym);
        if ($description !== null) $entity->setDescription($description);
        if ($active !== null) $entity->setActive($active);
        $this->em->flush();
        return $this->toArray($entity);
    }

    public function delete(string $entityClass, int $id): void
    {
        $entity = $this->em->getRepository($entityClass)->find($id);
        if ($entity === null) throw new NotFoundHttpException('Registro no encontrado.');
        $this->em->remove($entity);
        $this->em->flush();
    }

    private function toArray(object $entity): array
    {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'acronym' => $entity->getAcronym(),
            'description' => $entity->getDescription(),
            'active' => $entity->isActive(),
            'createdAt' => $entity->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $entity->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
