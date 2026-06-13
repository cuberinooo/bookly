<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Booking;
use App\Entity\CompanyAwareInterface;
use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Entity\GlobalSettings;
use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\Entity\TrainingCategory;
use App\Entity\TrainingCycle;
use App\Entity\User;
use App\Service\ApiCacheService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::postPersist, priority: 400, connection: 'default')]
#[AsDoctrineListener(event: Events::postUpdate, priority: 400, connection: 'default')]
#[AsDoctrineListener(event: Events::postRemove, priority: 400, connection: 'default')]
class CacheInvalidationSubscriber
{
    public function __construct(
        private readonly ApiCacheService $apiCache
    ) {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    private function invalidate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof CompanyAwareInterface && $entity->getCompany()) {
            $companyId = $entity->getCompany()->getId();

            $typesToInvalidate = $this->getInvalidationTypes($entity);
            foreach ($typesToInvalidate as $type) {
                $this->apiCache->invalidateEntity($type, $companyId);
            }
        } elseif ($entity instanceof User && $entity->getCompany()) {
            $companyId = $entity->getCompany()->getId();
            $this->apiCache->invalidateEntity('user', $companyId);
        } elseif ($entity instanceof GlobalSettings && $entity->getCompany()) {
            $companyId = $entity->getCompany()->getId();
            $this->apiCache->invalidateCompany($companyId);
        }
    }

    /**
     * @return string[]
     */
    private function getInvalidationTypes(object $entity): array
    {
        return match (true) {
            $entity instanceof Course => ['course'],
            $entity instanceof CourseSeries => ['course'],
            $entity instanceof Booking => ['course'],
            $entity instanceof Meetup => ['meetup'],
            $entity instanceof MeetupRsvp => ['meetup'],
            $entity instanceof User => ['user'],
            $entity instanceof TrainingCategory => ['trainingcategory', 'course'],
            $entity instanceof TrainingCycle => ['trainingcycle', 'course'],
            default => [],
        };
    }
}
