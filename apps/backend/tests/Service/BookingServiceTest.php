<?php

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Service\BookingService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BookingServiceTest extends TestCase
{
    private $entityManager;
    private $bookingRepository;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->service = new BookingService($this->entityManager, $this->bookingRepository);
    }

    public function testBookConfirmed(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('John Doe');
        
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);

        $course = $this->createMock(Course::class);
        $course->method('getTrainer')->willReturn($trainer);
        $course->method('getCapacity')->willReturn(10);
        $course->method('getBookings')->willReturn(new ArrayCollection());
        $course->method('getTitle')->willReturn('Yoga');

        $this->bookingRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        [$booking, $isWaitlist] = $this->service->book($course, $user);

        $this->assertFalse($isWaitlist);
        $this->assertSame($user, $booking->getMember());
        $this->assertSame($course, $booking->getCourse());
    }

    public function testBookWaitlist(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('Jane Doe');
        
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);

        $course = $this->createMock(Course::class);
        $course->method('getTrainer')->willReturn($trainer);
        $course->method('getCapacity')->willReturn(1);
        $course->method('getTitle')->willReturn('Pilates');

        $existingBooking = new Booking();
        $existingBooking->setWaitlist(false);
        $course->method('getBookings')->willReturn(new ArrayCollection([$existingBooking]));

        $this->bookingRepository->method('findOneBy')->willReturn(null);

        [$booking, $isWaitlist] = $this->service->book($course, $user);

        $this->assertTrue($isWaitlist);
        $this->assertTrue($booking->isWaitlist());
    }

    public function testBookAlreadyBookedThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(2);

        $course = $this->createMock(Course::class);
        $course->method('getTrainer')->willReturn($trainer);

        $this->bookingRepository->method('findOneBy')->willReturn(new Booking());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You already booked this course');

        $this->service->book($course, $user);
    }

    public function testBookOwnCourseThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        
        $course = $this->createMock(Course::class);
        $course->method('getTrainer')->willReturn($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('As a trainer, you cannot book your own course');

        $this->service->book($course, $user);
    }

    public function testUnbook(): void
    {
        $user = new User();
        $user->setName('Jane Doe');
        
        $trainer = new User();
        $course = $this->createMock(Course::class);
        $course->method('getTrainer')->willReturn($trainer);
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
