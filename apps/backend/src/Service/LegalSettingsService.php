<?php

namespace App\Service;

use App\Entity\LegalSettings;
use App\Repository\LegalSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class LegalSettingsService
{
    public function __construct(
        private LegalSettingsRepository $repository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private string $projectDir
    ) {}

    public function getSettings(): LegalSettings
    {
        return $this->repository->get();
    }

    public function updateSettings(array $data): LegalSettings
    {
        $settings = $this->repository->get();

        $fields = [
            'legalNoticeCompanyName',
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

        $this->entityManager->flush();

        return $settings;
    }

    public function uploadPrivacyPolicy(UploadedFile $file): string
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

        $settings = $this->repository->get();
        $settings->setPrivacyPolicyPdfPath('/uploads/legal/' . $newFilename);
        $this->entityManager->flush();

        return $settings->getPrivacyPolicyPdfPath();
    }
}
