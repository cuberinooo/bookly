<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Company;
use App\Entity\SmtpSettings;
use Doctrine\ORM\EntityManagerInterface;

class SmtpSettingsService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function updateSettings(Company $company, array $data): SmtpSettings
    {
        $settings = $company->getSmtpSettings();
        if (!$settings) {
            $settings = new SmtpSettings();
            $company->setSmtpSettings($settings);
            $settings->setCompany($company);
            $this->entityManager->persist($settings);
        }

        if (isset($data['host'])) {
            $settings->setHost($data['host']);
        }
        if (isset($data['port'])) {
            $settings->setPort((int) $data['port']);
        }
        if (isset($data['username'])) {
            $settings->setUsername($data['username']);
        }
        if (isset($data['password'])) {
            $settings->setPassword($data['password']);
        }
        if (isset($data['encryption'])) {
            $settings->setEncryption($data['encryption']);
        }
        if (isset($data['useCustomSmtp'])) {
            $settings->setUseCustomSmtp((bool) $data['useCustomSmtp']);
        }

        $this->entityManager->flush();

        return $settings;
    }
}
