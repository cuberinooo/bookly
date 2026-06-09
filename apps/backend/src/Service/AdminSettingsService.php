<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminSettings;
use App\Repository\AdminSettingsRepository;
use Aws\S3\S3ClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class AdminSettingsService
{
    public function __construct(
        private AdminSettingsRepository $repository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private S3ClientInterface $s3Client,
        private string $s3Bucket
    ) {
    }

    public function getSettingsByCompanyName(string $companyName): ?AdminSettings
    {
        return $this->repository->findOneByCompanyName($companyName);
    }

    public function updateSettings(\App\Entity\Company $company, array $data): AdminSettings
    {
        $settings = $company->getAdminSettings();

        $fields = [
            'legalNoticeRepresentative',
            'legalNoticeStreet',
            'legalNoticeHouseNumber',
            'legalNoticeZipCode',
            'legalNoticeCity',
            'legalNoticeEmail',
            'legalNoticePhone',
            'legalNoticeTaxId',
            'legalNoticeVatId',
            'legalNoticeMarkdown',
            'termsAndConditionsMarkdown',
            'welcomeMailMarkdown',
            'membershipWelcomeMailMarkdown',
            'homepageUrl',
            'companyLogoPath',
        ];


        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $setter = 'set'.ucfirst($field);
                $settings->$setter($data[$field]);
            }
        }

        if (array_key_exists('billingCycleAnchorDay', $data)) {
            $company->getStripeConfig()->setBillingCycleAnchorDay($data['billingCycleAnchorDay']);
        }

        if (array_key_exists('yearlyFeeEnabled', $data)) {
            $company->getStripeConfig()->setYearlyFeeEnabled((bool)$data['yearlyFeeEnabled']);
        }

        if (array_key_exists('paymentEnabled', $data)) {
            $company->getStripeConfig()->setPaymentEnabled((bool)$data['paymentEnabled']);
        }

        $this->entityManager->flush();

        return $settings;
    }

    public function uploadPrivacyPolicy(\App\Entity\Company $company, UploadedFile $file): string
    {
        $companySlug = $this->slugger->slug($company->getName())->lower();
        $key = $companySlug.'/legal/'.$this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid('legal', true).'.'.$file->guessExtension();

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
                'Body'   => fopen($file->getRealPath(), 'r'),
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload file to S3: '.$e->getMessage());
        }

        $settings = $company->getAdminSettings();
        $settings->setPrivacyPolicyPdfPath($key);
        $this->entityManager->flush();

        return $settings->getPrivacyPolicyPdfPath();
    }

    public function uploadWelcomeMailAttachment(\App\Entity\Company $company, UploadedFile $file): array
    {
        return $this->uploadAttachment($company, $file, 'welcome');
    }

    public function uploadMembershipWelcomeMailAttachment(\App\Entity\Company $company, UploadedFile $file): array
    {
        return $this->uploadAttachment($company, $file, 'membership-welcome');
    }

    private function uploadAttachment(\App\Entity\Company $company, UploadedFile $file, string $type): array
    {
        $companySlug = $this->slugger->slug($company->getName())->lower();
        $key = $companySlug.'/company_assets/'.$this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid('asset', true).'.'.$file->guessExtension();

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
                'Body'   => fopen($file->getRealPath(), 'r'),
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload file to S3: '.$e->getMessage());
        }

        $settings = $company->getAdminSettings();

        if ('welcome' === $type) {
            $attachments = $settings->getWelcomeMailAttachments() ?? [];
        } else {
            $attachments = $settings->getMembershipWelcomeMailAttachments() ?? [];
        }

        $attachment = [
            'name' => $file->getClientOriginalName(),
            'path' => $key,
        ];
        $attachments[] = $attachment;

        if ('welcome' === $type) {
            $settings->setWelcomeMailAttachments($attachments);
        } else {
            $settings->setMembershipWelcomeMailAttachments($attachments);
        }

        $this->entityManager->flush();

        return $attachment;
    }

    public function deleteWelcomeMailAttachment(\App\Entity\Company $company, string $path): void
    {
        $this->deleteAttachment($company, $path, 'welcome');
    }

    public function deleteMembershipWelcomeMailAttachment(\App\Entity\Company $company, string $path): void
    {
        $this->deleteAttachment($company, $path, 'membership-welcome');
    }

    private function deleteAttachment(\App\Entity\Company $company, string $path, string $type): void
    {
        $settings = $company->getAdminSettings();
        if ('welcome' === $type) {
            $attachments = $settings->getWelcomeMailAttachments() ?? [];
        } else {
            $attachments = $settings->getMembershipWelcomeMailAttachments() ?? [];
        }

        $newAttachments = [];
        foreach ($attachments as $att) {
            if ($att['path'] === $path) {
                try {
                    $this->s3Client->deleteObject([
                        'Bucket' => $this->s3Bucket,
                        'Key'    => $att['path'],
                    ]);
                } catch (\Exception $e) {
                    // Log error but continue
                }
            } else {
                $newAttachments[] = $att;
            }
        }

        if ('welcome' === $type) {
            $settings->setWelcomeMailAttachments($newAttachments);
        } else {
            $settings->setMembershipWelcomeMailAttachments($newAttachments);
        }

        $this->entityManager->flush();
    }

    public function uploadCompanyLogo(\App\Entity\Company $company, UploadedFile $file): string
    {
        $companySlug = $this->slugger->slug($company->getName())->lower();
        $extension = $file->guessExtension() ?? 'png';
        $filename = sprintf('logo_%s.%s', uniqid('', true), $extension);
        $key = $companySlug.'/logo/'.$filename;

        // Clean up existing logos
        $prefix = $companySlug.'/logo/';
        try {
            $this->s3Client->deleteMatchingObjects($this->s3Bucket, $prefix);
        } catch (\Exception $e) {
            // Ignore if nothing to delete or on error
        }

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
                'Body'   => fopen($file->getRealPath(), 'r'),
                'ContentType' => $file->getClientMimeType(),
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload company logo to S3: '.$e->getMessage());
        }

        $settings = $company->getAdminSettings();
        $settings->setCompanyLogoPath($key);
        $this->entityManager->flush();

        return $key;
    }

    public function deleteCompanyLogo(\App\Entity\Company $company): void
    {
        $settings = $company->getAdminSettings();
        $path = $settings->getCompanyLogoPath();
        if ($path) {
            try {
                $this->s3Client->deleteObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $path,
                ]);
            } catch (\Exception $e) {
                // Ignore
            }
            $settings->setCompanyLogoPath(null);
            $this->entityManager->flush();
        }
    }
}
