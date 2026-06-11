<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PlatformSettingsService;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3ClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/platform-settings')]
class PlatformSettingsController extends AbstractController
{
    public function __construct(
        private PlatformSettingsService $platformSettingsService,
        private SerializerInterface $serializer,
        private S3ClientInterface $s3Client,
        private string $s3Bucket
    ) {
    }

    #[Route('', name: 'platform_settings_public_get', methods: ['GET'])]
    public function getPublicSettings(): JsonResponse
    {
        $settings = $this->platformSettingsService->getSettings();
        $data = $this->serializer->serialize($settings, 'json', ['groups' => 'platform:read']);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/privacy-policy/download', name: 'platform_settings_public_download_privacy_policy', methods: ['GET'])]
    public function downloadPrivacyPolicy(): Response
    {
        $settings = $this->platformSettingsService->getSettings();
        $path = $settings->getPrivacyPolicyPdfPath();

        if (!$path) {
            return new Response('Privacy policy not found', Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $path,
            ]);

            $content = $result['Body']->getContents();
            $contentType = $result['ContentType'] ?? 'application/pdf';

            return new Response($content, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline; filename="privacy-policy.pdf"',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);

        } catch (S3Exception $e) {
            return new Response('Privacy policy file could not be retrieved from storage.', Response::HTTP_NOT_FOUND);
        }
    }
}
