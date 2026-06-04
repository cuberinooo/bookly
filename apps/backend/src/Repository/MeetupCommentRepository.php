<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MeetupComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MeetupComment>
 *
 * @method MeetupComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method MeetupComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method MeetupComment[]    findAll()
 * @method MeetupComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MeetupCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MeetupComment::class);
    }

    public function countUnreadComments(\App\Entity\User $user, \App\Entity\Meetup $meetup): int
    {
        $readState = $this->getEntityManager()->getRepository(\App\Entity\MeetupUserReadState::class)
            ->findOneBy(['user' => $user, 'meetup' => $meetup]);

        $qb = $this->createQueryBuilder('c')
            ->select('count(c.id)')
            ->where('c.meetup = :meetup')
            ->andWhere('c.author != :user')
            ->setParameter('meetup', $meetup)
            ->setParameter('user', $user);

        if ($readState) {
            $qb->andWhere('c.createdAt > :lastReadAt')
               ->setParameter('lastReadAt', $readState->getLastReadAt());
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
