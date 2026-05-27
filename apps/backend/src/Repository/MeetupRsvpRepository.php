<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MeetupRsvp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MeetupRsvp>
 */
class MeetupRsvpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MeetupRsvp::class);
    }
}
