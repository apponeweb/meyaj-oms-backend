<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppFunction;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\AppFunctionRepository;
use App\Repository\AppModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seguridad/functions')]
#[OA\Tag(name: 'Seguridad - Funcionalidades')]
final class AppFunctionController extends AbstractController
{
    public function __construct(
        private readonly AppFunctionRepository $repo,
        private readonly AppModuleRepository $moduleRepo,
        private readonly EntityManagerInterface $em,
        private readonly Paginator $paginator,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(
        Request $request,
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        $moduleId = $request->query->get('moduleId');

        $qb = $this->repo->createQueryBuilder('f')
            ->join('f.appModule', 'm')
            ->addSelect('m');

        if ($moduleId) {
            $qb->andWhere('f.appModule = :mid')->setParameter('mid', $moduleId);
        }
        if ($pagination->search) {
            $qb->andWhere('f.name LIKE :s OR f.code LIKE :s')->setParameter('s', "%{$pagination->search}%");
        }

        if ($pagination->sort === null) {
            $qb->orderBy('m.displayOrder', 'ASC')->addOrderBy('f.displayOrder', 'ASC');
        }

        $page = $this->paginator->paginate($qb, $pagination);

        $data = array_map(static fn (AppFunction $f) => [
            'id' => $f->getId(),
            'code' => $f->getCode(),
            'name' => $f->getName(),
            'moduleId' => $f->getAppModule()->getId(),
            'moduleName' => $f->getAppModule()->getName(),
            'displayOrder' => $f->getDisplayOrder(),
            'active' => $f->isActive(),
            'createdAt' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $page->data);

        return $this->json(['data' => $data, 'meta' => $page->meta]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $module = $this->moduleRepo->find($data['moduleId'] ?? 0);
        if (!$module) return $this->json(['error' => 'Módulo no encontrado'], 422);

        $f = new AppFunction();
        $f->setAppModule($module);
        $f->setCode($data['code']);
        $f->setName($data['name']);
        $f->setDisplayOrder($data['displayOrder'] ?? 0);

        $this->em->persist($f);
        $this->em->flush();

        return $this->json(['id' => $f->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $f = $this->repo->find($id);
        if (!$f) return $this->json(['error' => 'No encontrado'], 404);

        $data = $request->toArray();
        if (isset($data['code'])) $f->setCode($data['code']);
        if (isset($data['name'])) $f->setName($data['name']);
        if (isset($data['displayOrder'])) $f->setDisplayOrder($data['displayOrder']);
        if (isset($data['active'])) $f->setActive($data['active']);
        if (isset($data['moduleId'])) {
            $module = $this->moduleRepo->find($data['moduleId']);
            if ($module) $f->setAppModule($module);
        }

        $this->em->flush();

        return $this->json(['id' => $f->getId()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $f = $this->repo->find($id);
        if (!$f) return $this->json(['error' => 'No encontrado'], 404);

        $this->em->remove($f);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
