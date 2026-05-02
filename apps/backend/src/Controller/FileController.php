<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    #[Route('/uploads/{path}', name: 'uploads_serve', requirements: ['path' => '.+'], methods: ['GET'])]
    public function serve(string $path): Response
    {
        $uploadDir = $this->getParameter('upload_dir');
        $fullPath = $uploadDir . '/' . $path;

        if (!file_exists($fullPath)) {
            throw $this->createNotFoundException('File not found');
        }

        return $this->file($fullPath);
    }
}
