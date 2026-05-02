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

    public function findOneByCompanyName(string $name): ?AdminSettings
    {
        return $this->createQueryBuilder('a')
            ->join('App\Entity\Company', 'c', 'WITH', 'c.adminSettings = a')
            ->where('c.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
