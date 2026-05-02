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
            'termsAndConditionsMarkdown'
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
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid('bookly', true) . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->projectDir . '/public/uploads/legal',
                $newFilename
            );
        } catch (FileException $e) {
            throw new \RuntimeException('Failed to upload file');
        }

        $settings = $company->getAdminSettings();
        $settings->setPrivacyPolicyPdfPath('/uploads/legal/' . $newFilename);
        $this->entityManager->flush();

        return $settings->getPrivacyPolicyPdfPath();
    }
}
