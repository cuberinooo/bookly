<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminSettingsService;
use Aws\S3\S3ClientInterface;
use Stripe\Stripe;
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
        private \Doctrine\ORM\EntityManagerInterface $entityManager,
        private S3ClientInterface $s3Client,
        private string $s3Bucket,
        private string $stripeSecretKey
    ) {
    }

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
        $data['stripeOnboardingComplete'] = $company->getStripeConfig()->isStripeOnboardingComplete();
        $data['stripeAccountId'] = $company->getStripeConfig()->getStripeAccountId();
        $data['stripePriceSetupFeeId'] = $company->getStripeConfig()->getStripePriceSetupFeeId();
        $data['stripePriceMembershipId'] = $company->getStripeConfig()->getStripePriceMembershipId();
        $data['billingCycleAnchorDay'] = $company->getStripeConfig()->getBillingCycleAnchorDay();
        $data['yearlyFeeEnabled'] = $company->getStripeConfig()->isYearlyFeeEnabled();
        $data['paymentEnabled'] = $company->getStripeConfig()->isPaymentEnabled();

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
        $company = $user->getCompany();

        if (array_key_exists('paymentEnabled', $data)) {
            $newValue = (bool)$data['paymentEnabled'];
            $oldValue = $company->getStripeConfig()->isPaymentEnabled();

            if ($oldValue && !$newValue) {
                // Check for active subscriptions in Stripe
                $stripeAccountId = $company->getStripeConfig()->getStripeAccountId();
                if ($stripeAccountId) {
                    Stripe::setApiKey($this->stripeSecretKey);
                    $subscriptions = \Stripe\Subscription::all([
                        'status' => 'active',
                        'limit' => 1,
                    ], ['stripe_account' => $stripeAccountId]);

                    if (count($subscriptions->data) > 0) {
                        return new JsonResponse(['error' => 'Cannot disable payments while active subscriptions exist.'], Response::HTTP_BAD_REQUEST);
                    }
                }
            }
        }

        $this->adminSettingsService->updateSettings($company, $data);

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

    #[Route('/membership-welcome-attachment', name: 'admin_settings_upload_membership_welcome_attachment', methods: ['POST'])]
    public function uploadMembershipWelcomeMailAttachment(Request $request): JsonResponse
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
            $attachment = $this->adminSettingsService->uploadMembershipWelcomeMailAttachment($user->getCompany(), $file);

            return new JsonResponse($attachment);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/membership-welcome-attachment', name: 'admin_settings_delete_membership_welcome_attachment', methods: ['DELETE'])]
    public function deleteMembershipWelcomeMailAttachment(Request $request): JsonResponse
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

        $this->adminSettingsService->deleteMembershipWelcomeMailAttachment($user->getCompany(), $path);

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
            return new Response('Settings not found for company: '.$companyName, Response::HTTP_NOT_FOUND);
        }

        $path = $settings->getPrivacyPolicyPdfPath();

        if (!$path) {
            return new Response('Privacy policy not found', Response::HTTP_NOT_FOUND);
        }
        try {
            // 1. Fetch the file directly from S3/MinIO
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $path,
            ]);

            // 2. Extract the file content and mime-type
            $content = $result['Body']->getContents();
            $contentType = $result['ContentType'] ?? 'application/pdf';

            // 3. Return the PDF directly in the response
            return new Response($content, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="privacy-policy.pdf"',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);

        } catch (S3Exception $e) {
            return new Response('Privacy policy file could not be retrieved from storage.', Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/welcome-attachment/download', name: 'admin_settings_download_welcome_attachment', methods: ['GET'])]
    public function downloadWelcomeMailAttachment(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !$user->getCompany()) {
            return new Response('Company not found', Response::HTTP_NOT_FOUND);
        }

        $path = $request->query->get('path');
        if (!$path) {
            return new Response('No path provided', Response::HTTP_BAD_REQUEST);
        }

        $settings = $user->getCompany()->getAdminSettings();
        $welcomeAttachments = $settings->getWelcomeMailAttachments() ?? [];
        $membershipWelcomeAttachments = $settings->getMembershipWelcomeMailAttachments() ?? [];
        $attachments = array_merge($welcomeAttachments, $membershipWelcomeAttachments);

        $found = false;
        $fileName = 'attachment';
        foreach ($attachments as $att) {
            if ($att['path'] === $path) {
                $found = true;
                $fileName = $att['name'];
                break;
            }
        }

        if (!$found) {
            return new Response('Attachment not found', Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $path,
            ]);

            $content = $result['Body']->getContents();
            $contentType = $result['ContentType'] ?? 'application/octet-stream';

            return new Response($content, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);

        } catch (\Exception $e) {
            return new Response('Attachment file could not be retrieved from storage.', Response::HTTP_NOT_FOUND);
        }
    }
}
