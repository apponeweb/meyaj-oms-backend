<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppModule;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\AppModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seguridad/modules')]
#[OA\Tag(name: 'Seguridad - Módulos')]
final class AppModuleController extends AbstractController
{
    public function __construct(
        private readonly AppModuleRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly Paginator $paginator,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();

        $qb = $this->repo->createQueryBuilder('m');

        if ($pagination->search) {
            $qb->andWhere('m.name LIKE :s OR m.code LIKE :s')->setParameter('s', "%{$pagination->search}%");
        }

        if ($pagination->sort === null) {
            $qb->orderBy('m.displayOrder', 'ASC');
        }

        $page = $this->paginator->paginate($qb, $pagination);

        $data = array_map(static fn (AppModule $m) => [
            'id' => $m->getId(),
            'code' => $m->getCode(),
            'name' => $m->getName(),
            'icon' => $m->getIcon(),
            'displayOrder' => $m->getDisplayOrder(),
            'active' => $m->isActive(),
            'createdAt' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $page->data);

        return $this->json(['data' => $data, 'meta' => $page->meta]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $m = new AppModule();
        $m->setCode($data['code']);
        $m->setName($data['name']);
        $m->setIcon($data['icon'] ?? 'box');
        $m->setDisplayOrder($data['displayOrder'] ?? 0);

        $this->em->persist($m);
        $this->em->flush();

        return $this->json(['id' => $m->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $m = $this->repo->find($id);
        if (!$m) return $this->json(['error' => 'No encontrado'], 404);

        $data = $request->toArray();
        if (isset($data['code'])) $m->setCode($data['code']);
        if (isset($data['name'])) $m->setName($data['name']);
        if (isset($data['icon'])) $m->setIcon($data['icon']);
        if (isset($data['displayOrder'])) $m->setDisplayOrder($data['displayOrder']);
        if (isset($data['active'])) $m->setActive($data['active']);

        $this->em->flush();

        return $this->json(['id' => $m->getId()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $m = $this->repo->find($id);
        if (!$m) return $this->json(['error' => 'No encontrado'], 404);

        $this->em->remove($m);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
