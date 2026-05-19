<?php

namespace App\EventListener;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\Service\MercurePublisherService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::postPersist, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postRemove, priority: 500, connection: 'default')]
class MercureEntityListener
{
    private const SUPPORTED_ENTITIES = [
        Booking::class,
        Course::class,
        CourseSeries::class,
        Meetup::class,
        MeetupRsvp::class,
    ];

    public function __construct(
        private readonly MercurePublisherService $mercurePublisher
    ) {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->publish($args, 'created');
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->publish($args, 'updated');
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->publish($args, 'deleted');
    }

    private function publish(LifecycleEventArgs $args, string $action): void
    {
        $entity = $args->getObject();

        if ($this->isSupported($entity)) {
            $this->mercurePublisher->publishEntityUpdate($entity, $action);

            // If an RSVP changes, also notify the meetup topic so counts can be updated
            if ($entity instanceof MeetupRsvp && $entity->getMeetup()) {
                $this->mercurePublisher->publishEntityUpdate($entity->getMeetup(), 'updated');
            }
        }
    }

    private function isSupported(object $entity): bool
    {
        foreach (self::SUPPORTED_ENTITIES as $supportedEntity) {
            if ($entity instanceof $supportedEntity) {
                return true;
            }
        }

        return false;
    }
}
