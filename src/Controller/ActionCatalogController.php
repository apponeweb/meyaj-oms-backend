<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActionCatalog;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\ActionCatalogRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seguridad/actions')]
#[OA\Tag(name: 'Seguridad - Acciones')]
final class ActionCatalogController extends AbstractController
{
    public function __construct(
        private readonly ActionCatalogRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly Paginator $paginator,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();

        $qb = $this->repo->createQueryBuilder('a');

        if ($pagination->search) {
            $qb->andWhere('a.name LIKE :s OR a.code LIKE :s')->setParameter('s', "%{$pagination->search}%");
        }

        if ($pagination->sort === null) {
            $qb->orderBy('a.id', 'ASC');
        }

        $page = $this->paginator->paginate($qb, $pagination);

        $data = array_map(static fn (ActionCatalog $a) => [
            'id' => $a->getId(),
            'code' => $a->getCode(),
            'name' => $a->getName(),
        ], $page->data);

        return $this->json(['data' => $data, 'meta' => $page->meta]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $a = new ActionCatalog();
        $a->setCode($data['code']);
        $a->setName($data['name']);

        $this->em->persist($a);
        $this->em->flush();

        return $this->json(['id' => $a->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $a = $this->repo->find($id);
        if (!$a) return $this->json(['error' => 'No encontrado'], 404);

        $data = $request->toArray();
        if (isset($data['code'])) $a->setCode($data['code']);
        if (isset($data['name'])) $a->setName($data['name']);

        $this->em->flush();

        return $this->json(['id' => $a->getId()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $a = $this->repo->find($id);
        if (!$a) return $this->json(['error' => 'No encontrado'], 404);

        $this->em->remove($a);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
