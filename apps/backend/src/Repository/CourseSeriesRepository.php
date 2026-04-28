<?php

namespace App\Repository;

use App\Entity\CourseSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CourseSeries>
 */
class CourseSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseSeries::class);
    }

    /**
     * @return CourseSeries[]
     */
    public function findActiveSeries(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.active = :active')
            ->andWhere('s.frequency != :once')
            ->setParameter('active', true)
            ->setParameter('once', \App\Enum\CourseFrequency::ONCE)
            ->getQuery()
            ->getResult();
    }
}
