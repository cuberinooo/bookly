<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\EventListener\MercureEntityListener;
use App\Service\MercurePublisherService;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\TestCase;

class MercureEntityListenerTest extends TestCase
{
    public function test_post_persist_publishes_entity_update(): void
    {
        $mercurePublisher = $this->createMock(MercurePublisherService::class);
        $listener = new MercureEntityListener($mercurePublisher);

        $meetup = $this->createMock(Meetup::class);
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($meetup);

        $mercurePublisher->expects($this->once())
            ->method('publishEntityUpdate')
            ->with($meetup, 'created');

        $listener->postPersist($args);
    }

    public function test_meetup_rsvp_persist_triggers_meetup_update(): void
    {
        $mercurePublisher = $this->createMock(MercurePublisherService::class);
        $listener = new MercureEntityListener($mercurePublisher);

        $meetup = $this->createMock(Meetup::class);
        $rsvp = $this->createMock(MeetupRsvp::class);
        $rsvp->method('getMeetup')->willReturn($meetup);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($rsvp);

        // Should publish for RSVP AND for the related Meetup
        $mercurePublisher->expects($this->exactly(2))
            ->method('publishEntityUpdate')
            ->willReturnCallback(function ($entity, $action) use ($rsvp, $meetup) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame($rsvp, $entity);
                    $this->assertSame('created', $action);
                } else {
                    $this->assertSame($meetup, $entity);
                    $this->assertSame('updated', $action);
                }
            });

        $listener->postPersist($args);
    }
}
