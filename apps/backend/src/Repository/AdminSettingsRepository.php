<?php

namespace App\Repository;

use App\Entity\AdminSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminSettings>
 */
class AdminSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminSettings::class);
    }

    public function get(): AdminSettings
    {
        $settings = $this->findOneBy([]);
        if (!$settings) {
            $settings = new AdminSettings();
            $this->getEntityManager()->persist($settings);
            $this->getEntityManager()->flush();
        }

        return $settings;
    }
}
