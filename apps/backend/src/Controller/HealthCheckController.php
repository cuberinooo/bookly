<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheckController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
