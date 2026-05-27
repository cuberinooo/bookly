<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserWorkoutRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserWorkoutRecord>
 *
 * @method UserWorkoutRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserWorkoutRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserWorkoutRecord[] findAll()
 * @method UserWorkoutRecord[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserWorkoutRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserWorkoutRecord::class);
    }

    public function save(UserWorkoutRecord $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserWorkoutRecord $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Returns the highest weight per exercise for each user.
     */
    public function findTopRecordsByExercise(): array
    {
        return $this->createQueryBuilder('r')
            ->select('u.id as userId', 'u.name as userName', 'u.profilePicture as profilePicture', 'u.gender as gender', 'r.exerciseName as exerciseName', 'MAX(r.weightValue) as maxWeight', 'MAX(r.dateAchieved) as dateAchieved')
            ->join('r.user', 'u')
            ->where('u.isPublic = :isPublic')
            ->setParameter('isPublic', true)
            ->groupBy('u.id', 'u.name', 'u.profilePicture', 'u.gender', 'r.exerciseName')
            ->orderBy('r.exerciseName', 'ASC')
            ->addOrderBy('maxWeight', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}
