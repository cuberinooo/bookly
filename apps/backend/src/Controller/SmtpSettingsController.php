<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\SmtpSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/smtp-settings')]
class SmtpSettingsController extends AbstractController
{
    public function __construct(
        private SmtpSettingsService $smtpSettingsService,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'smtp_settings_get', methods: ['GET'])]
    public function getSettings(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getCompany()) {
            return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $settings = $user->getCompany()->getSmtpSettings();

        return new JsonResponse(
            $this->serializer->serialize($settings, 'json', ['groups' => 'admin:read']),
            Response::HTTP_OK,
            [],
            true
        );
    }

    #[Route('', name: 'smtp_settings_update', methods: ['PATCH'])]
    public function updateSettings(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getCompany()) {
            return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $settings = $this->smtpSettingsService->updateSettings($user->getCompany(), $data);

        return new JsonResponse(
            $this->serializer->serialize($settings, 'json', ['groups' => 'admin:read']),
            Response::HTTP_OK,
            [],
            true
        );
    }
}
