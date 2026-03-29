<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/upload')]
#[OA\Tag(name: 'Archivos')]
final class UploadController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Subir un archivo')]
    #[OA\Response(response: 201, description: 'Archivo subido exitosamente')]
    #[OA\Response(response: 400, description: 'No se propocionó archivo')]
    public function upload(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';
        
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $file->move($destination, $newFilename);

        return $this->json([
            'url' => '/uploads/' . $newFilename,
            'filename' => $newFilename
        ], Response::HTTP_CREATED);
    }
}
