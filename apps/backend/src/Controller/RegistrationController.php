<?php

namespace App\Controller;

use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, RegistrationService $registrationService, #[Autowire(service: 'limiter.registration')] RateLimiterFactory $registrationLimiter): JsonResponse {
        $limiter = $registrationLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Too many registration attempts. Please try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['name'])) {
            return new JsonResponse(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $registrationService->register($data);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['status' => 'User created. Please check your email to verify your account.'], Response::HTTP_CREATED);
    }

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request, RegistrationService $registrationService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            return new JsonResponse(['error' => 'Missing token'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $registrationService->verifyEmail($token);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['status' => 'Email verified successfully']);
    }

    #[Route('/api/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request, RegistrationService $registrationService): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'Missing email'], Response::HTTP_BAD_REQUEST);
        }

        $registrationService->resendVerification($email);

        return new JsonResponse(['status' => 'If your account exists and is not verified, a new link has been sent.']);
    }

    #[Route('/api/register/roles', name: 'api_register_roles', methods: ['GET'])]
    public function getRoles(): JsonResponse
    {
        return new JsonResponse([
            ['label' => 'Member', 'value' => 'ROLE_MEMBER'],
            ['label' => 'Trainer', 'value' => 'ROLE_TRAINER'],
            ['label' => 'Admin', 'value' => 'ROLE_ADMIN']
        ]);
    }
}
