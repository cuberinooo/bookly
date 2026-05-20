<?php

namespace App\Repository;

use App\Entity\TrainingCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingCategory>
 */
class TrainingCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingCategory::class);
    }

    /**
     * @return TrainingCategory[]
     */
    public function findByTrainer(int $trainerId): array
    {
        return $this->createQueryBuilder('tc')
            ->andWhere('tc.trainer = :trainerId')
            ->setParameter('trainerId', $trainerId)
            ->getQuery()
            ->enableResultCache(3600)
            ->getResult();
    }
}
