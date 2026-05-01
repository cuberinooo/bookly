<?php

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\GlobalSettingsRepository;
use App\Service\BookingService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

class BookingServiceTest extends TestCase
{
    private $entityManager;
    private $bookingRepository;
    private $settingsRepository;
    private $mailer;
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
        $this->service = new BookingService(
            $this->entityManager,
            $this->bookingRepository,
            $this->settingsRepository,
            $this->mailer
        );

        $this->defaultCompany = new \App\Entity\Company();
        $this->defaultSettings = new GlobalSettings();
        $this->defaultCompany->setGlobalSettings($this->defaultSettings);
    }

    public function testBookConfirmed(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('John Doe');

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);
        $trainer->method('getCompany')->willReturn($this->defaultCompany);

        $course = new Course();
        $course->setEndTime(new \DateTime('+1 hour'));
        $course->setUser($trainer);
        $course->setCapacity(10);
        $course->setTitle('Yoga');

        $this->bookingRepository->method('findOneBy')->willReturn(null);
        $this->bookingRepository->method('count')->willReturn(0);

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        [$booking, $isWaitlist] = $this->service->book($course, $user);

        $this->assertFalse($isWaitlist);
        $this->assertSame($user, $booking->getUser());
        $this->assertSame($course, $booking->getCourse());
    }

    public function testBookWaitlist(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('Jane Doe');

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);
        $trainer->method('getCompany')->willReturn($this->defaultCompany);

        $course = new Course();
        $course->setEndTime(new \DateTime('+1 hour'));
        $course->setUser($trainer);
        $course->setCapacity(1);
        $course->setTitle('Pilates');

        $existingBooking = new Booking();
        $existingBooking->setWaitlist(false);

        $this->bookingRepository->method('findOneBy')->willReturn(null);
        $this->bookingRepository->method('count')->willReturn(1);

        [$booking, $isWaitlist] = $this->service->book($course, $user);

        $this->assertTrue($isWaitlist);
        $this->assertTrue($booking->isWaitlist());
    }

    public function testWaitlistPromotion(): void
    {
        $user = new User();
        $user->setName('Jane Doe');

        $trainer = new User();
        $trainer->setCompany($this->defaultCompany);
        $course = new Course();
        $course->setEndTime(new \DateTime('+1 hour'));
        $course->setUser($trainer);
        $course->setTitle('Pilates');
        $course->setCapacity(1);
        $course->setStartTime(new \DateTime('+1 day'));

        $booking = new Booking();
        $booking->setWaitlist(false);
        $booking->setCourse($course);
        $this->bookingRepository->method('findOneBy')->willReturn($booking);

        // Mock promotion logic
        $waitlistedUser = new User();
        $waitlistedUser->setName('Waiting User');
        $waitlistedUser->setEmail('waiting@example.com');

        $waitlistedBooking = new Booking();
        $waitlistedBooking->setUser($waitlistedUser);
        $waitlistedBooking->setCourse($course);
        $waitlistedBooking->setWaitlist(true);

        $this->bookingRepository->method('count')->willReturn(0);
        $this->bookingRepository->method('findNextInWaitlist')->willReturnOnConsecutiveCalls($waitlistedBooking, null);

        $this->mailer->expects($this->once())->method('send');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->service->unbook($course, $user);

        $this->assertFalse($waitlistedBooking->isWaitlist());
    }

    public function testBookAlreadyBookedThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);
        $trainer->method('getCompany')->willReturn($this->defaultCompany);

        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getUser')->willReturn($trainer);

        $this->bookingRepository->method('findOneBy')->willReturn(new Booking());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You already booked this course');

        $this->service->book($course, $user);
    }

    public function testBookOwnCourseThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getCompany')->willReturn($this->defaultCompany);

        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getUser')->willReturn($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('As a trainer, you cannot book your own course');

        $this->service->book($course, $user);
    }

    public function testUnbook(): void
    {
        $user = new User();
        $user->setName('Jane Doe');

        $trainer = new User();
        $trainer->setCompany($this->defaultCompany);
        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('+1 hour'));
        $course->method('getUser')->willReturn($trainer);
        $course->method('getTitle')->willReturn('Pilates');

        $booking = new Booking();
        $this->bookingRepository->method('findOneBy')->willReturn($booking);

        $this->entityManager->expects($this->once())->method('remove')->with($booking);
        $this->entityManager->expects($this->once())->method('persist'); // notification
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->unbook($course, $user);
    }

    public function testDeleteBooking(): void
    {
        $booking = new Booking();
        $this->entityManager->expects($this->once())->method('remove')->with($booking);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->deleteBooking($booking);
    }

    public function testBookPastCourseThrowsException(): void
    {
        $user = new User();
        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('-1 hour'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You cannot book a course that has already finished');

        $this->service->book($course, $user);
    }

    public function testUnbookPastCourseThrowsException(): void
    {
        $user = new User();
        $course = $this->createMock(Course::class);
        $course->method('getEndTime')->willReturn(new \DateTime('-1 hour'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You cannot cancel a booking for a course that has already finished');

        $this->service->unbook($course, $user);
    }

    public function testRemoveBookingIfExists(): void
    {
        $user = new User();
        $course = new Course();
        $booking = new Booking();

        $this->bookingRepository->method('findOneBy')->willReturn($booking);
        $this->entityManager->expects($this->once())->method('remove')->with($booking);

        $this->service->removeBookingIfExists($course, $user);
    }
}
