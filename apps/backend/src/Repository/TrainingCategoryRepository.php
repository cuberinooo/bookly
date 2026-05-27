<?php

declare(strict_types=1);

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
    public function findByCompany(): array
    {
        return $this->createQueryBuilder('tc')
            ->getQuery()
            ->getResult();
    }
}
