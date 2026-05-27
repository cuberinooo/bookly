<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SensitiveDataAccessLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SensitiveDataAccessLog>
 */
class SensitiveDataAccessLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SensitiveDataAccessLog::class);
    }
}
