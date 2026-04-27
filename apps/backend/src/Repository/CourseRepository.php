<?php

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /**
     * @return Course[]
     */
    public function findOverlappingCourses(\DateTimeInterface $startTime, \DateTimeInterface $endTime, ?int $excludeId = null, ?int $trainerId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.startTime < :endTime')
            ->andWhere('c.endTime > :startTime');

        if ($excludeId) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        if ($trainerId) {
            $qb->andWhere('c.trainer = :trainerId')
                ->setParameter('trainerId', $trainerId);
        }

        return $qb->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Course[]
     */
    public function findAllFutureCourses(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.endTime >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('c.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds courses with pagination and date filtering.
     */
    public function findPaginated(int $page, int $limit, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null, bool $futureOnly = false, ?int $trainerId = null, ?int $memberId = null): array
    {
        $qb = $this->createQueryBuilder('c');

        if ($futureOnly && !$startDate) {
            $qb->andWhere('c.endTime >= :now')
               ->setParameter('now', new \DateTime());
        } elseif ($startDate) {
            $qb->andWhere('c.endTime >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('c.startTime <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if ($trainerId) {
            $qb->andWhere('c.trainer = :trainerId')
               ->setParameter('trainerId', $trainerId);
        }

        if ($memberId) {
            $qb->join('c.bookings', 'b')
               ->andWhere('b.member = :memberId')
               ->setParameter('memberId', $memberId);
        }

        $qb->orderBy('c.startTime', 'ASC');

        // Get total count
        $countQb = clone $qb;
        $totalItems = (int) $countQb->select('COUNT(c.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        // Get results
        $results = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $results,
            'totalItems' => $totalItems,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($totalItems / $limit)
        ];
    }
}
