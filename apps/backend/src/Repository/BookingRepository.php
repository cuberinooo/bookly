<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findNextInWaitlist(Course $course): ?Booking
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.course = :course')
            ->andWhere('b.isWaitlist = :isWaitlist')
            ->setParameter('course', $course)
            ->setParameter('isWaitlist', true)
            ->orderBy('b.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
