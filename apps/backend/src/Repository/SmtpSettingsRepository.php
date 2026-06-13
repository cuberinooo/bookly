<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SmtpSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SmtpSettings>
 *
 * @method SmtpSettings|null find($id, $lockMode = null, $lockVersion = null)
 * @method SmtpSettings|null findOneBy(array $criteria, array $orderBy = null)
 * @method SmtpSettings[] findAll()
 * @method SmtpSettings[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SmtpSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmtpSettings::class);
    }
}
