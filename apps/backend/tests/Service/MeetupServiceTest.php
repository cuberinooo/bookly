<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\Entity\User;
use App\Enum\MeetupStatus;
use App\Enum\RsvpStatus;
use App\Service\EmailService;
use App\Service\MeetupService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MeetupServiceTest extends TestCase
{
    private $entityManager;
    private $emailService;
    private $translator;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->service = new MeetupService(
            $this->entityManager,
            $this->emailService,
            $this->translator
        );
    }

    public function test_create_meetup(): void
    {
        $company = new Company();
        $creator = new User();
        $creator->setCompany($company);

        $data = [
            'title' => 'Hiking',
            'location' => 'Mountains',
            'meetupDate' => '2026-07-01 10:00:00',
            'sendNotification' => true,
        ];

        $userRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(User::class)->willReturn($userRepo);
        $userRepo->method('findBy')->willReturn([new User()]);

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $meetup = $this->service->createMeetup($data, $creator);

        $this->assertInstanceOf(Meetup::class, $meetup);
        $this->assertEquals('Hiking', $meetup->getTitle());
    }

    public function test_create_meetup_with_invalid_deadline(): void
    {
        $creator = new User();
        $data = [
            'title' => 'Hiking',
            'location' => 'Mountains',
            'meetupDate' => '2026-07-01 10:00:00',
            'rsvpDeadline' => '2026-07-02 10:00:00', // After meetup
        ];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('error.rsvp_deadline_after_meetup');

        $this->service->createMeetup($data, $creator);
    }

    public function test_update_meetup_with_invalid_deadline(): void
    {
        $creator = new User();
        $meetup = new Meetup();
        $meetup->setCreator($creator);
        $meetup->setMeetupDate(new \DateTime('2026-07-01 10:00:00'));

        $data = [
            'rsvpDeadline' => '2026-07-02 10:00:00', // After meetup
        ];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('error.rsvp_deadline_after_meetup');

        $this->service->updateMeetup($meetup, $data, $creator);
    }

    public function test_rsvp_freeze_logic(): void
    {
        $user = new User();
        $meetup = new Meetup();
        $meetup->setRsvpDeadline(new \DateTime('-1 hour'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('error.rsvp_window_closed');

        $this->service->handleRsvp($meetup, $user, RsvpStatus::GOING);
    }

    public function test_capacity_limit(): void
    {
        $user = new User();
        $meetup = $this->createMock(Meetup::class);
        $meetup->method('getRsvpDeadline')->willReturn(null);
        $meetup->method('getStatus')->willReturn(MeetupStatus::OPEN);
        $meetup->method('getMaxParticipants')->willReturn(2);
        $meetup->method('getGoingCount')->willReturn(2);

        $rsvpRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(MeetupRsvp::class)->willReturn($rsvpRepo);
        $rsvpRepo->method('findOneBy')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('error.meetup_full');

        $this->service->handleRsvp($meetup, $user, RsvpStatus::GOING);
    }

    public function test_evaluate_meetup_status_confirmed(): void
    {
        $meetup = $this->createMock(Meetup::class);
        $meetup->method('getStatus')->willReturn(MeetupStatus::OPEN);
        $meetup->method('getMinParticipants')->willReturn(2);
        $meetup->method('getGoingCount')->willReturn(3);
        
        $rsvp = new MeetupRsvp();
        $rsvp->setStatus(RsvpStatus::GOING);
        $meetup->method('getRsvps')->willReturn(new ArrayCollection([$rsvp]));

        $meetup->expects($this->once())->method('setStatus')->with(MeetupStatus::CONFIRMED);
        $this->emailService->expects($this->once())->method('sendParticipantsOfConfirmation');

        $this->service->evaluateMeetupStatus($meetup);
    }

    public function test_evaluate_meetup_status_cancelled(): void
    {
        $meetup = $this->createMock(Meetup::class);
        $meetup->method('getStatus')->willReturn(MeetupStatus::OPEN);
        $meetup->method('getMinParticipants')->willReturn(5);
        $meetup->method('getGoingCount')->willReturn(3);

        $rsvp = new MeetupRsvp();
        $rsvp->setStatus(RsvpStatus::GOING);
        $meetup->method('getRsvps')->willReturn(new ArrayCollection([$rsvp]));

        $meetup->expects($this->once())->method('setStatus')->with(MeetupStatus::CANCELLED);
        $this->emailService->expects($this->once())->method('sendParticipantsOfCancellation');

        $this->service->evaluateMeetupStatus($meetup);
    }

    public function test_cancel_meetup_declines_everyone(): void
    {
        $creator = new User();
        $meetup = new Meetup();
        $meetup->setCreator($creator);
        
        $rsvp = new MeetupRsvp();
        $rsvp->setStatus(RsvpStatus::GOING);
        $meetup->addRsvp($rsvp);

        $this->service->cancelMeetup($meetup, $creator);

        $this->assertEquals(MeetupStatus::CANCELLED, $meetup->getStatus());
        $this->assertEquals(RsvpStatus::NOT_GOING, $rsvp->getStatus());
    }
}
