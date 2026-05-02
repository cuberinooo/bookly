<?php

namespace App\Controller;

use App\Service\AdminSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin-settings')]
class AdminSettingsController extends AbstractController
{
    public function __construct(
        private AdminSettingsService $adminSettingsService,
        private SerializerInterface $serializer,
        private \Doctrine\ORM\EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_settings_get', methods: ['GET'])]
    public function getSettings(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !$user->getCompany()) {
             return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $company = $user->getCompany();
        $settings = $company->getAdminSettings();
        
        $data = json_decode($this->serializer->serialize($settings, 'json', ['groups' => 'admin:read']), true);
        $data['name'] = $company->getName();
        
        return new JsonResponse($data);
    }

    #[Route('', name: 'admin_settings_update', methods: ['PATCH'])]
    public function updateSettings(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !$user->getCompany()) {
             return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $this->adminSettingsService->updateSettings($user->getCompany(), $data);

        return new JsonResponse(['status' => 'Admin settings updated']);
    }

    #[Route('/privacy-policy', name: 'admin_settings_upload_privacy_policy', methods: ['POST'])]
    public function uploadPrivacyPolicy(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !$user->getCompany()) {
             return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $path = $this->adminSettingsService->uploadPrivacyPolicy($user->getCompany(), $file);
            return new JsonResponse(['path' => $path]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/welcome-attachment', name: 'admin_settings_upload_welcome_attachment', methods: ['POST'])]
    public function uploadWelcomeMailAttachment(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !$user->getCompany()) {
             return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $attachment = $this->adminSettingsService->uploadWelcomeMailAttachment($user->getCompany(), $file);
            return new JsonResponse($attachment);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/welcome-attachment', name: 'admin_settings_delete_welcome_attachment', methods: ['DELETE'])]
    public function deleteWelcomeMailAttachment(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !$user->getCompany()) {
             return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $path = $request->query->get('path');
        if (!$path) {
            return new JsonResponse(['error' => 'No path provided'], Response::HTTP_BAD_REQUEST);
        }

        $this->adminSettingsService->deleteWelcomeMailAttachment($user->getCompany(), $path);

        return new JsonResponse(['status' => 'Attachment deleted']);
    }

    #[Route('/privacy-policy/download', name: 'admin_settings_download_privacy_policy', methods: ['GET'])]
    public function downloadPrivacyPolicy(Request $request): Response
    {
        $companyName = $request->query->get('companyName');
        if (!$companyName) {
            // Fallback to current user's company if authenticated
            $user = $this->getUser();
            if ($user instanceof \App\Entity\User && $user->getCompany()) {
                $companyName = $user->getCompany()->getName();
            }
        }

        if (!$companyName) {
            return new Response('Company name not provided', Response::HTTP_BAD_REQUEST);
        }

        $settings = $this->adminSettingsService->getSettingsByCompanyName($companyName);
        if (!$settings) {
            return new Response('Settings not found for company: ' . $companyName, Response::HTTP_NOT_FOUND);
        }

        $path = $settings->getPrivacyPolicyPdfPath();

        if (!$path) {
            return new Response('Privacy policy not found', Response::HTTP_NOT_FOUND);
        }

        // Remove /uploads/ prefix from path when using upload_dir
        $relativePaths = str_replace('/uploads/', '', $path);
        $fullPath = $this->getParameter('upload_dir') . '/' . $relativePaths;

        if (!file_exists($fullPath)) {
            return new Response('File not found', Response::HTTP_NOT_FOUND);
        }

        return $this->file($fullPath, 'privacy-policy.pdf');
    }
}
