<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;

class BookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository
    ) {
    }

    /**
     * Books a course for a user.
     *
     * @return array [Booking $booking, bool $isWaitlist]
     * @throws \Exception if already booked
     */
    public function book(Course $course, User $user): array
    {
        // Check if already booked
        $existingBooking = $this->bookingRepository->findOneBy(['member' => $user, 'course' => $course]);
        if ($existingBooking) {
            throw new \Exception('You already booked this course');
        }

        // Waitlist logic: if count of confirmed bookings >= capacity, it's a waitlist booking
        $confirmedBookings = array_filter($course->getBookings()->toArray(), fn($b) => !$b->isWaitlist());
        $isWaitlist = count($confirmedBookings) >= $course->getCapacity();

        $booking = new Booking();
        $booking->setMember($user);
        $booking->setCourse($course);
        $booking->setWaitlist($isWaitlist);

        $this->entityManager->persist($booking);

        // Notify trainer
        $notification = new Notification();
        $notification->setUser($course->getTrainer());
        $statusMsg = $isWaitlist ? 'joined the waitlist for' : 'has joined';
        $notification->setMessage(sprintf('%s %s your course "%s"', $user->getName(), $statusMsg, $course->getTitle()));
        $this->entityManager->persist($notification);

        $this->entityManager->flush();

        return [$booking, $isWaitlist];
    }

    /**
     * Unbooks a course for a user.
     *
     * @throws \Exception if booking not found
     */
    public function unbook(Course $course, User $user): void
    {
        $booking = $this->bookingRepository->findOneBy(['member' => $user, 'course' => $course]);
        if (!$booking) {
            throw new \Exception('Booking not found');
        }

        $this->entityManager->remove($booking);

        // Notify trainer
        $notification = new Notification();
        $notification->setUser($course->getTrainer());
        $notification->setMessage(sprintf('%s has left your course "%s"', $user->getName(), $course->getTitle()));
        $this->entityManager->persist($notification);

        $this->entityManager->flush();
    }

    /**
     * Deletes a specific booking (called by trainer).
     */
    public function deleteBooking(Booking $booking): void
    {
        $this->entityManager->remove($booking);
        $this->entityManager->flush();
    }
}
