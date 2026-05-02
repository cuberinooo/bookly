<?php

namespace App\Service;

use App\Entity\AdminSettings;
use App\Repository\AdminSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class AdminSettingsService
{
    public function __construct(
        private AdminSettingsRepository $repository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private string $projectDir
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

        if (isset($data['name'])) {
            $company->setName((string) $data['name']);
        }

        $this->entityManager->flush();

        return $settings;
    }

    public function uploadPrivacyPolicy(\App\Entity\Company $company, UploadedFile $file): string
    {
        $companySlug = $this->slugger->slug($company->getName())->lower();
        $uploadDir = $this->projectDir . '/public/uploads/' . $companySlug . '/legal';

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid('legal', true) . '.' . $file->guessExtension();

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Failed to upload file');
        }

        $settings = $company->getAdminSettings();
        $settings->setPrivacyPolicyPdfPath('/uploads/' . $companySlug . '/legal/' . $newFilename);
        $this->entityManager->flush();

        return $settings->getPrivacyPolicyPdfPath();
    }

    public function uploadWelcomeMailAttachment(\App\Entity\Company $company, UploadedFile $file): array
    {
        $companySlug = $this->slugger->slug($company->getName())->lower();
        $uploadDir = $this->projectDir . '/public/uploads/' . $companySlug . '/company_assets';

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid('asset', true) . '.' . $file->guessExtension();

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Failed to upload file');
        }

        $settings = $company->getAdminSettings();
        $attachments = $settings->getWelcomeMailAttachments() ?? [];
        $attachment = [
            'name' => $file->getClientOriginalName(),
            'path' => '/uploads/' . $companySlug . '/company_assets/' . $newFilename
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
                $fullPath = $this->projectDir . '/public' . $att['path'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            } else {
                $newAttachments[] = $att;
            }
        }
        
        $settings->setWelcomeMailAttachments($newAttachments);
        $this->entityManager->flush();
    }
}
