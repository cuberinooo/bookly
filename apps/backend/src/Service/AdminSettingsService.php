<?php

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
    ) {}

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
            'welcomeMailMarkdown'
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $setter = 'set' . ucfirst($field);
                $settings->$setter($data[$field]);
            }
        }

        $this->entityManager->flush();

        return $settings;
    }

    public function uploadPrivacyPolicy(\App\Entity\Company $company, UploadedFile $file): string
    {
        $companySlug = $this->slugger->slug($company->getName())->lower();
        $key = $companySlug . '/legal/' . $this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . uniqid('legal', true) . '.' . $file->guessExtension();

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
                'Body'   => fopen($file->getRealPath(), 'r'),
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload file to S3: ' . $e->getMessage());
        }

        $settings = $company->getAdminSettings();
        $settings->setPrivacyPolicyPdfPath($key);
        $this->entityManager->flush();

        return $settings->getPrivacyPolicyPdfPath();
    }

    public function uploadWelcomeMailAttachment(\App\Entity\Company $company, UploadedFile $file): array
    {
        $companySlug = $this->slugger->slug($company->getName())->lower();
        $key = $companySlug . '/company_assets/' . $this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . uniqid('asset', true) . '.' . $file->guessExtension();

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
                'Body'   => fopen($file->getRealPath(), 'r'),
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload file to S3: ' . $e->getMessage());
        }

        $settings = $company->getAdminSettings();
        $attachments = $settings->getWelcomeMailAttachments() ?? [];
        $attachment = [
            'name' => $file->getClientOriginalName(),
            'path' => $key
        ];
        $attachments[] = $attachment;
        $settings->setWelcomeMailAttachments($attachments);
        $this->entityManager->flush();

        return $attachment;
    }

    public function deleteWelcomeMailAttachment(\App\Entity\Company $company, string $path): void
    {
        $settings = $company->getAdminSettings();
        $attachments = $settings->getWelcomeMailAttachments() ?? [];

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

        $settings->setWelcomeMailAttachments($newAttachments);
        $this->entityManager->flush();
    }
}
