<?php

namespace App\Repository;

use App\Entity\TrainingCycle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingCycle>
 */
class TrainingCycleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingCycle::class);
    }

    public function findActiveCycleForTrainer(int $trainerId): ?TrainingCycle
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.trainer = :trainerId')
            ->andWhere('c.isActive = :isActive')
            ->setParameter('trainerId', $trainerId)
            ->setParameter('isActive', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
