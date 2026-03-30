<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateTagRequest;
use App\DTO\Request\UpdateTagRequest;
use App\DTO\Response\TagResponse;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class TagService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TagRepository $tagRepository,
    ) {}

    /** @return TagResponse[] */
    public function list(?bool $active = null): array
    {
        $criteria = [];
        if ($active !== null) {
            $criteria['active'] = $active;
        }
        $tags = $this->tagRepository->findBy($criteria, ['name' => 'ASC']);
        return array_map(static fn (Tag $t) => new TagResponse($t), $tags);
    }

    public function show(int $id): TagResponse
    {
        $t = $this->tagRepository->find($id);
        if ($t === null) throw new NotFoundHttpException(sprintf('Etiqueta con ID %d no encontrada.', $id));
        return new TagResponse($t);
    }

    public function create(CreateTagRequest $request): TagResponse
    {
        $t = new Tag();
        $t->setName($request->name);
        $t->setColor($request->color);
        $t->setDescription($request->description);
        $this->em->persist($t);
        $this->em->flush();
        return new TagResponse($t);
    }

    public function update(int $id, UpdateTagRequest $request): TagResponse
    {
        $t = $this->tagRepository->find($id);
        if ($t === null) throw new NotFoundHttpException(sprintf('Etiqueta con ID %d no encontrada.', $id));
        if ($request->name !== null) $t->setName($request->name);
        if ($request->color !== null) $t->setColor($request->color);
        if ($request->description !== null) $t->setDescription($request->description);
        if ($request->active !== null) $t->setActive($request->active);
        $this->em->flush();
        return new TagResponse($t);
    }

    public function delete(int $id): void
    {
        $t = $this->tagRepository->find($id);
        if ($t === null) throw new NotFoundHttpException(sprintf('Etiqueta con ID %d no encontrada.', $id));
        $this->em->remove($t);
        $this->em->flush();
    }
}
