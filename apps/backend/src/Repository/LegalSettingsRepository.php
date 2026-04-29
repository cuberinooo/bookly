<?php

namespace App\Repository;

use App\Entity\LegalSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalSettings>
 */
class LegalSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalSettings::class);
    }

    public function get(): LegalSettings
    {
        $settings = $this->findOneBy([]);
        if (!$settings) {
            $settings = new LegalSettings();
            $this->getEntityManager()->persist($settings);
            $this->getEntityManager()->flush();
        }
        return $settings;
    }
}
