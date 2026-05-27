<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\OnboardingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/user/me/onboarding')]
class OnboardingController extends AbstractController
{
    public function __construct(
        private OnboardingService $onboardingService,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'user_onboarding_update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['skip']) && $data['skip'] === true) {
            $this->onboardingService->skipOnboarding($user);
        } elseif (isset($data['taskId']) && is_string($data['taskId'])) {
            $this->onboardingService->markTaskComplete($user, $data['taskId']);
        } else {
            return new JsonResponse(['error' => 'Invalid data. Provide taskId or skip: true.'], Response::HTTP_BAD_REQUEST);
        }

        // Return updated user state
        $json = $this->serializer->serialize($user, 'json', ['groups' => 'user:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
}
