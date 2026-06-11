<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlatformSettings;
use App\Repository\PlatformSettingsRepository;
use Aws\S3\S3ClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class PlatformSettingsService
{
    public function __construct(
        private PlatformSettingsRepository $repository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private S3ClientInterface $s3Client,
        private string $s3Bucket
    ) {
    }

    public function getSettings(): PlatformSettings
    {
        return $this->repository->get();
    }

    public function updateSettings(array $data): PlatformSettings
    {
        $settings = $this->repository->get();

        $fields = [
            'operatorName',
            'operatorCompany',
            'operatorDetails',
            'operatorStreet',
            'operatorHouseNumber',
            'operatorZipCode',
            'operatorCity',
            'operatorEmail',
            'operatorPhone',
            'profession',
            'country',
            'taxId',
            'vatId',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $setter = 'set'.ucfirst($field);
                $settings->$setter($data[$field]);
            }
        }

        $this->entityManager->flush();

        return $settings;
    }

    public function uploadPrivacyPolicy(UploadedFile $file): string
    {
        $key = 'platform/legal/'.$this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid('legal', true).'.'.$file->guessExtension();

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
                'Body'   => fopen($file->getRealPath(), 'r'),
                'ContentType' => $file->getClientMimeType() ?? 'application/pdf',
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload file to S3: '.$e->getMessage());
        }

        $settings = $this->repository->get();

        $oldPath = $settings->getPrivacyPolicyPdfPath();
        if ($oldPath && $oldPath !== $key) {
            try {
                $this->s3Client->deleteObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $oldPath,
                ]);
            } catch (\Exception $e) {
                // Ignore error on deletion of old file
            }
        }

        $settings->setPrivacyPolicyPdfPath($key);
        $this->entityManager->flush();

        return $settings->getPrivacyPolicyPdfPath();
    }
}
