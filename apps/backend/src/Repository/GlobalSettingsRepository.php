<?php

namespace App\Repository;

use App\Entity\GlobalSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GlobalSettings>
 */
class GlobalSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GlobalSettings::class);
    }

    public function get(): GlobalSettings
    {
        $settings = $this->findOneBy([]);
        if (!$settings) {
            $settings = new GlobalSettings();
            $this->getEntityManager()->persist($settings);
            $this->getEntityManager()->flush();
        }
        return $settings;
    }
}
