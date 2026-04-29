<?php

namespace App\Controller;

use App\Service\LegalSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/legal-settings')]
class LegalSettingsController extends AbstractController
{
    public function __construct(
        private LegalSettingsService $legalSettingsService,
        private SerializerInterface $serializer
    ) {}

    #[Route('', name: 'legal_settings_get', methods: ['GET'])]
    public function getSettings(): JsonResponse
    {
        $settings = $this->legalSettingsService->getSettings();
        $json = $this->serializer->serialize($settings, 'json', ['groups' => 'legal:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'legal_settings_update', methods: ['PATCH'])]
    public function updateSettings(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $settings = $this->legalSettingsService->updateSettings($data);

        return new JsonResponse(['status' => 'Legal settings updated']);
    }

    #[Route('/privacy-policy', name: 'legal_settings_upload_privacy_policy', methods: ['POST'])]
    public function uploadPrivacyPolicy(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $path = $this->legalSettingsService->uploadPrivacyPolicy($file);
            return new JsonResponse(['path' => $path]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
