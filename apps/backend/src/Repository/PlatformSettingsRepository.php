<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlatformSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformSettings>
 */
class PlatformSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformSettings::class);
    }

    public function get(): PlatformSettings
    {
        $settings = $this->findOneBy([]);
        if (!$settings) {
            $settings = new PlatformSettings();
            $this->getEntityManager()->persist($settings);
            $this->getEntityManager()->flush();
        }
        return $settings;
    }
}
