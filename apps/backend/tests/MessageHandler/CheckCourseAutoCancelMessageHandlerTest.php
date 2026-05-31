<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Booking;
use App\Entity\Company;
use App\Entity\Course;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Message\CheckCourseAutoCancelMessage;
use App\MessageHandler\CheckCourseAutoCancelMessageHandler;
use App\Repository\CourseRepository;
use App\Service\CourseService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\Translation\TranslatorInterface;

class CheckCourseAutoCancelMessageHandlerTest extends TestCase
{
    private $courseRepository;
    private $courseService;
    private $entityManager;
    private $messageBus;
    private $mailer;
    private $translator;
    private $handler;

    protected function setUp(): void
    {
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->courseService = $this->createMock(CourseService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        // MessageBus dispatch returns Envelope (final class)
        $this->messageBus->method('dispatch')->willReturnCallback(function($message, $stamps = []) {
            return new Envelope($message, $stamps);
        });

        $this->handler = new CheckCourseAutoCancelMessageHandler(
            $this->courseRepository,
            $this->courseService,
            $this->entityManager,
            $this->messageBus,
            $this->mailer,
            $this->translator
        );
    }

    public function test_skips_if_disabled(): void
    {
        $settings = new GlobalSettings();
        $settings->setAutoCancelEnabled(false);

        $company = new Company();
        $company->setGlobalSettings($settings);

        $course = $this->createMock(Course::class);
        $course->method('getStatus')->willReturn(CourseStatus::ACTIVE);
        $course->method('getCompany')->willReturn($company);

        $this->courseRepository->method('find')->willReturn($course);

        $this->courseService->expects($this->never())->method('cancelCourse');
        
        ($this->handler)(new CheckCourseAutoCancelMessage(1));
    }

    public function test_re_dispatches_if_too_early(): void
    {
        $settings = new GlobalSettings();
        $settings->setAutoCancelEnabled(true);
        $settings->setAutoCancelHoursBefore(4);

        $company = new Company();
        $company->setGlobalSettings($settings);

        $course = $this->createMock(Course::class);
        $course->method('getStatus')->willReturn(CourseStatus::ACTIVE);
        $course->method('getCompany')->willReturn($company);
        // Course starts in 10 hours. Check should happen 4 hours before (in 6 hours).
        $course->method('getStartTime')->willReturn(new \DateTime('+10 hours'));

        $this->courseRepository->method('find')->willReturn($course);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CheckCourseAutoCancelMessage::class), $this->callback(function($stamps) {
                return $stamps[0] instanceof DelayStamp;
            }))
            ->willReturnCallback(function($message, $stamps = []) {
                return new Envelope($message, $stamps);
            });

        $this->courseService->expects($this->never())->method('cancelCourse');
        
        ($this->handler)(new CheckCourseAutoCancelMessage(1));
    }

    public function test_cancels_if_under_threshold(): void
    {
        $settings = new GlobalSettings();
        $settings->setAutoCancelEnabled(true);
        $settings->setAutoCancelMinParticipants(3);
        $settings->setAutoCancelHoursBefore(4);

        $company = new Company();
        $company->setName('Test Gym');
        $company->setGlobalSettings($settings);

        $trainer = new User();
        $trainer->setEmail('trainer@example.com');
        $trainer->setName('Trainer Joe');

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(1);
        $course->method('getTitle')->willReturn('Morning Yoga');
        $course->method('getStatus')->willReturn(CourseStatus::ACTIVE);
        $course->method('getCompany')->willReturn($company);
        $course->method('getUser')->willReturn($trainer);
        // Course starts in 3 hours (inside the 4-hour window)
        $course->method('getStartTime')->willReturn(new \DateTime('+3 hours'));
        
        // 1 confirmed booking, 1 waitlist
        $b1 = new Booking(); $b1->setWaitlist(false);
        $b2 = new Booking(); $b2->setWaitlist(true);
        $course->method('getBookings')->willReturn(new ArrayCollection([$b1, $b2]));

        $this->courseRepository->method('find')->willReturn($course);

        $this->courseService->expects($this->once())
            ->method('cancelCourse')
            ->with($course, null);
        
        $this->mailer->expects($this->once())->method('send');
        
        ($this->handler)(new CheckCourseAutoCancelMessage(1));
    }

    public function test_does_not_cancel_if_threshold_met(): void
    {
        $settings = new GlobalSettings();
        $settings->setAutoCancelEnabled(true);
        $settings->setAutoCancelMinParticipants(3);
        $settings->setAutoCancelHoursBefore(4);

        $company = new Company();
        $company->setGlobalSettings($settings);

        $course = $this->createMock(Course::class);
        $course->method('getStatus')->willReturn(CourseStatus::ACTIVE);
        $course->method('getCompany')->willReturn($company);
        $course->method('getStartTime')->willReturn(new \DateTime('+3 hours'));
        
        // 3 confirmed bookings
        $b1 = new Booking(); $b1->setWaitlist(false);
        $b2 = new Booking(); $b2->setWaitlist(false);
        $b3 = new Booking(); $b3->setWaitlist(false);
        $course->method('getBookings')->willReturn(new ArrayCollection([$b1, $b2, $b3]));

        $this->courseRepository->method('find')->willReturn($course);

        $this->courseService->expects($this->never())->method('cancelCourse');
        
        ($this->handler)(new CheckCourseAutoCancelMessage(1));
    }
}
