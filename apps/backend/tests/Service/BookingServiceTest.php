<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\GlobalSettingsRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingServiceTest extends TestCase
{
    private $entityManager;
    private $bookingRepository;
    private $settingsRepository;
    private $mailer;
    private $translator;
    private $service;
    private $defaultCompany;
    private $defaultSettings;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->settingsRepository = $this->createMock(GlobalSettingsRepository::class);
        $this->settingsRepository->method('find')->willReturn(new GlobalSettings());
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->service = new BookingService(
            $this->entityManager,
            $this->bookingRepository,
            $this->settingsRepository,
            $this->mailer,
            $this->translator
        );

        $this->defaultCompany = new \App\Entity\Company();
        $this->defaultSettings = new GlobalSettings();
        $this->defaultCompany->setGlobalSettings($this->defaultSettings);
    }

    public function test_book_confirmed(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('John Doe');
        $user->method('getEmail')->willReturn('john@example.com');

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);
        $trainer->method('getCompany')->willReturn($this->defaultCompany);

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(10);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getStartTime')->willReturn(new \DateTime('+30 minutes'));
        $course->method('getUser')->willReturn($trainer);
        $course->method('getCapacity')->willReturn(10);
        $course->method('getTitle')->willReturn('Yoga');
        $course->method('getCompany')->willReturn($this->defaultCompany);
        $course->method('getStatus')->willReturn(\App\Enum\CourseStatus::ACTIVE);

        $this->bookingRepository->method('findOneBy')->willReturn(null);
        $this->bookingRepository->method('count')->willReturn(0);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        [$booking, $isWaitlist] = $this->service->book($course, $user);

        $this->assertFalse($isWaitlist);
        $this->assertSame($user, $booking->getUser());
        $this->assertSame($course, $booking->getCourse());
    }

    public function test_book_waitlist(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('Jane Doe');

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);
        $trainer->method('getCompany')->willReturn($this->defaultCompany);

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(11);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getUser')->willReturn($trainer);
        $course->method('getCapacity')->willReturn(1);
        $course->method('getTitle')->willReturn('Pilates');
        $course->method('getCompany')->willReturn($this->defaultCompany);
        $course->method('getStatus')->willReturn(\App\Enum\CourseStatus::ACTIVE);

        $this->bookingRepository->method('findOneBy')->willReturn(null);
        $this->bookingRepository->method('count')->willReturn(1);

        [$booking, $isWaitlist] = $this->service->book($course, $user);

        $this->assertTrue($isWaitlist);
        $this->assertTrue($booking->isWaitlist());
    }

    public function test_waitlist_promotion(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('Jane Doe');
        $user->method('getEmail')->willReturn('jane@example.com');

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);
        $trainer->method('getCompany')->willReturn($this->defaultCompany);

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(11);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getStartTime')->willReturn(new \DateTime('+1 day'));
        $course->method('getUser')->willReturn($trainer);
        $course->method('getCapacity')->willReturn(1);
        $course->method('getTitle')->willReturn('Pilates');
        $course->method('getCompany')->willReturn($this->defaultCompany);
        $course->method('getStatus')->willReturn(\App\Enum\CourseStatus::ACTIVE);

        $booking = new Booking();
        $booking->setWaitlist(false);
        $booking->setCourse($course);
        $booking->setUser($user);
        $this->bookingRepository->method('findOneBy')->willReturn($booking);

        // Mock promotion logic
        $waitlistedUser = $this->createMock(User::class);
        $waitlistedUser->method('getId')->willReturn(3);
        $waitlistedUser->method('getName')->willReturn('Waiting User');
        $waitlistedUser->method('getEmail')->willReturn('waiting@example.com');

        $waitlistedBooking = new Booking();
        $waitlistedBooking->setUser($waitlistedUser);
        $waitlistedBooking->setCourse($course);
        $waitlistedBooking->setWaitlist(true);

        $this->bookingRepository->method('count')->willReturn(0);
        $this->bookingRepository->method('findNextInWaitlist')->willReturnOnConsecutiveCalls($waitlistedBooking, null);

        $this->mailer->expects($this->atLeastOnce())->method('send');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->service->unbook($course, $user);

        $this->assertFalse($waitlistedBooking->isWaitlist());
    }

    public function test_book_already_booked_throws_exception(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);
        $trainer->method('getCompany')->willReturn($this->defaultCompany);

        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getUser')->willReturn($trainer);
        $course->method('getStatus')->willReturn(\App\Enum\CourseStatus::ACTIVE);

        $this->bookingRepository->method('findOneBy')->willReturn(new Booking());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error.already_booked');

        $this->service->book($course, $user);
    }

    public function test_book_own_course_throws_exception(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getCompany')->willReturn($this->defaultCompany);

        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getUser')->willReturn($user);
        $course->method('getStatus')->willReturn(\App\Enum\CourseStatus::ACTIVE);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error.trainer_cannot_book_own');

        $this->service->book($course, $user);
    }

    public function test_unbook(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('Jane Doe');
        $user->method('getEmail')->willReturn('jane@example.com');

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);
        $trainer->method('getCompany')->willReturn($this->defaultCompany);

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(11);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getStartTime')->willReturn(new \DateTime('+30 minutes'));
        $course->method('getUser')->willReturn($trainer);
        $course->method('getTitle')->willReturn('Pilates');
        $course->method('getCompany')->willReturn($this->defaultCompany);

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setCourse($course);
        $this->bookingRepository->method('findOneBy')->willReturn($booking);

        $this->entityManager->expects($this->once())->method('remove')->with($booking);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->unbook($course, $user);
    }

    public function test_delete_booking(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('Jane Doe');
        $user->method('getEmail')->willReturn('jane@example.com');

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(11);
        $course->method('getStartTime')->willReturn(new \DateTime('+30 minutes'));
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getTitle')->willReturn('Yoga');
        $course->method('getCompany')->willReturn($this->defaultCompany);

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setCourse($course);

        $this->entityManager->expects($this->once())->method('remove')->with($booking);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->deleteBooking($booking);
    }

    public function test_book_postponed_course_throws_exception(): void
    {
        $user = new User();
        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getStatus')->willReturn(\App\Enum\CourseStatus::POSTPONED);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error.course_postponed_no_book');

        $this->service->book($course, $user);
    }

    public function test_book_past_course_throws_exception(): void
    {
        $user = new User();
        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('-1 hour'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error.cannot_book_finished');

        $this->service->book($course, $user);
    }

    public function test_unbook_past_course_throws_exception(): void
    {
        $user = new User();
        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('-1 hour'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error.cannot_cancel_finished');

        $this->service->unbook($course, $user);
    }

    public function test_remove_booking_if_exists(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('Jane Doe');
        $user->method('getEmail')->willReturn('jane@example.com');

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(11);
        $course->method('getStartTime')->willReturn(new \DateTime('+30 minutes'));
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getTitle')->willReturn('Yoga');
        $course->method('getCompany')->willReturn($this->defaultCompany);

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setCourse($course);

        $this->bookingRepository->method('findOneBy')->willReturn($booking);
        $this->entityManager->expects($this->once())->method('remove')->with($booking);

        $this->service->removeBookingIfExists($course, $user);
    }
}
