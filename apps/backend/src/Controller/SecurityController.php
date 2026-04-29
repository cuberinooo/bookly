<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse(['message' => 'Logged out successfully']);

        // Clear the refresh token cookie
        $response->headers->setCookie(
            new Cookie(
                'refresh_token',
                null,
                1,
                '/api/token/refresh',
                null,
                true, // Secure
                true, // HttpOnly
                false,
                'none' // SameSite
            )
        );

        return $response;
    }
}
