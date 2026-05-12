<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\Entity\User;
use App\Enum\MeetupStatus;
use App\Enum\RsvpStatus;
use App\Service\MeetupService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

class MeetupServiceTest extends TestCase
{
    private $entityManager;
    private $mailer;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->service = new MeetupService(
            $this->entityManager,
            $this->mailer
        );
    }

    public function testCreateMeetup(): void
    {
        $creator = new User();
        $company = new Company();
        $creator->setCompany($company);

        $data = [
            'title' => 'Hiking Trip',
            'description' => 'Hiking in the Alps',
            'meetupDate' => '2026-06-01 10:00:00',
            'location' => 'Innsbruck',
            'rsvpDeadline' => '2026-05-25 10:00:00',
            'sendNotification' => false
        ];

        $meetup = $this->service->createMeetup($data, $creator);

        $this->assertEquals('Hiking Trip', $meetup->getTitle());
        $this->assertEquals($creator, $meetup->getCreator());
        $this->assertEquals($company, $meetup->getCompany());
        $this->assertEquals(MeetupStatus::OPEN, $meetup->getStatus());
    }

    public function testCreateMeetupWithInvalidDeadline(): void
    {
        $creator = new User();
        $company = new Company();
        $creator->setCompany($company);

        $data = [
            'title' => 'Invalid Deadline',
            'meetupDate' => '2026-06-01 10:00:00',
            'location' => 'Innsbruck',
            'rsvpDeadline' => '2026-06-01 11:00:00', // After meetupDate
        ];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("The RSVP deadline cannot be after the meetup date.");

        $this->service->createMeetup($data, $creator);
    }

    public function testUpdateMeetupWithInvalidDeadline(): void
    {
        $creator = new User();
        $meetup = new Meetup();
        $meetup->setCreator($creator);
        $meetup->setMeetupDate(new \DateTime('2026-06-01 10:00:00'));
        $meetup->setRsvpDeadline(new \DateTime('2026-05-30 10:00:00'));

        $data = [
            'rsvpDeadline' => '2026-06-02 10:00:00', // After meetupDate
        ];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("The RSVP deadline cannot be after the meetup date.");

        $this->service->updateMeetup($meetup, $data, $creator);
    }

    public function testRsvpFreezeLogic(): void
    {
        $meetup = new Meetup();
        // Deadline in the past
        $meetup->setRsvpDeadline(new \DateTime('-1 hour'));
        $meetup->setStatus(MeetupStatus::OPEN);
        
        $user = new User();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("The RSVP window is closed.");

        $this->service->handleRsvp($meetup, $user, RsvpStatus::GOING);
    }

    public function testCapacityLimit(): void
    {
        $meetup = new Meetup();
        $meetup->setRsvpDeadline(new \DateTime('+1 day'));
        $meetup->setMaxParticipants(1);
        
        $user1 = new User();
        $user2 = new User();

        $rsvpRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->willReturn($rsvpRepo);
        
        $rsvp1 = new MeetupRsvp();
        $rsvp1->setUser($user1);
        $rsvp1->setStatus(RsvpStatus::GOING);
        $meetup->addRsvp($rsvp1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Meetup is full.");

        $this->service->handleRsvp($meetup, $user2, RsvpStatus::GOING);
    }

    public function testEvaluateMeetupStatusConfirmed(): void
    {
        $company = new Company();
        $company->setName('Test Company');
        
        $meetup = new Meetup();
        $meetup->setCompany($company);
        $meetup->setMinParticipants(2);
        $meetup->setStatus(MeetupStatus::OPEN);

        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user2 = new User();
        $user2->setEmail('user2@example.com');

        $rsvp1 = new MeetupRsvp();
        $rsvp1->setUser($user1);
        $rsvp1->setStatus(RsvpStatus::GOING);
        $meetup->addRsvp($rsvp1);

        $rsvp2 = new MeetupRsvp();
        $rsvp2->setUser($user2);
        $rsvp2->setStatus(RsvpStatus::GOING);
        $meetup->addRsvp($rsvp2);

        $this->service->evaluateMeetupStatus($meetup);

        $this->assertEquals(MeetupStatus::CONFIRMED, $meetup->getStatus());
    }

    public function testEvaluateMeetupStatusCancelled(): void
    {
        $company = new Company();
        $company->setName('Test Company');

        $meetup = new Meetup();
        $meetup->setCompany($company);
        $meetup->setMinParticipants(2);
        $meetup->setStatus(MeetupStatus::OPEN);

        $user1 = new User();
        $user1->setEmail('user1@example.com');

        $rsvp1 = new MeetupRsvp();
        $rsvp1->setUser($user1);
        $rsvp1->setStatus(RsvpStatus::GOING);
        $meetup->addRsvp($rsvp1);

        $this->service->evaluateMeetupStatus($meetup);

        $this->assertEquals(MeetupStatus::CANCELLED, $meetup->getStatus());
        $this->assertEquals(RsvpStatus::NOT_GOING, $rsvp1->getStatus());
    }

    public function testCancelMeetupDeclinesEveryone(): void
    {
        $meetup = new Meetup();
        $creator = new User();
        $meetup->setCreator($creator);
        $meetup->setStatus(MeetupStatus::OPEN);
        
        $company = new Company();
        $company->setName('Test');
        $meetup->setCompany($company);

        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $rsvp1 = new MeetupRsvp();
        $rsvp1->setUser($user1);
        $rsvp1->setStatus(RsvpStatus::GOING);
        $meetup->addRsvp($rsvp1);

        $this->service->cancelMeetup($meetup, $creator);

        $this->assertEquals(MeetupStatus::CANCELLED, $meetup->getStatus());
        $this->assertEquals(RsvpStatus::NOT_GOING, $rsvp1->getStatus());
    }
}
