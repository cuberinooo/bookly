<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class BookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private MailerInterface $mailer
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
        // Check if the course is already done
        if ($course->getEndTime() < new \DateTime()) {
            throw new \Exception('You cannot book a course that has already finished');
        }

        // Check if the user is the trainer of the course
        if ($course->getTrainer()->getId() === $user->getId()) {
            throw new \Exception('As a trainer, you cannot book your own course');
        }

        // Check if already booked
        $existingBooking = $this->bookingRepository->findOneBy(['member' => $user, 'course' => $course]);
        if ($existingBooking) {
            throw new \Exception('You already booked this course');
        }

        // Waitlist logic: if count of confirmed bookings >= capacity, it's a waitlist booking
        $confirmedBookingsCount = $this->bookingRepository->count(['course' => $course, 'isWaitlist' => false]);
        $isWaitlist = $confirmedBookingsCount >= $course->getCapacity();

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
        // Check if the course is already done
        if ($course->getEndTime() < new \DateTime()) {
            throw new \Exception('You cannot cancel a booking for a course that has already finished');
        }

        $booking = $this->bookingRepository->findOneBy(['member' => $user, 'course' => $course]);
        if (!$booking) {
            throw new \Exception('Booking not found');
        }

        $wasWaitlist = $booking->isWaitlist();
        $this->entityManager->remove($booking);

        // Notify trainer
        $notification = new Notification();
        $notification->setUser($course->getTrainer());
        $notification->setMessage(sprintf('%s has left your course "%s"', $user->getName(), $course->getTitle()));
        $this->entityManager->persist($notification);

        $this->entityManager->flush();

        // If a non-waitlist booking was removed, try to promote someone from waitlist
        if (!$wasWaitlist) {
            $this->processWaitlist($course);
        }
    }

    /**
     * Deletes a specific booking (called by trainer).
     */
    public function deleteBooking(Booking $booking): void
    {
        $course = $booking->getCourse();
        $wasWaitlist = $booking->isWaitlist();

        $this->entityManager->remove($booking);
        $this->entityManager->flush();

        if (!$wasWaitlist && $course) {
            $this->processWaitlist($course);
        }
    }

    /**
     * Removes a booking for a specific user and course if it exists.
     */
    public function removeBookingIfExists(Course $course, User $user): void
    {
        $booking = $this->bookingRepository->findOneBy(['member' => $user, 'course' => $course]);
        if ($booking) {
            $wasWaitlist = $booking->isWaitlist();
            $this->entityManager->remove($booking);
            $this->entityManager->flush();

            if (!$wasWaitlist) {
                $this->processWaitlist($course);
            }
        }
    }

    private function processWaitlist(Course $course): void
    {
        // Check if there is space now
        $confirmedBookingsCount = $this->bookingRepository->count(['course' => $course, 'isWaitlist' => false]);

        if ($confirmedBookingsCount < $course->getCapacity()) {
            $nextInWaitlist = $this->bookingRepository->findNextInWaitlist($course);

            if ($nextInWaitlist) {
                $nextInWaitlist->setWaitlist(false);
                $this->entityManager->flush();

                $this->sendWaitlistPromotedEmail($nextInWaitlist);

                // Recursively check if there's more space (e.g. if capacity was increased)
                $this->processWaitlist($course);
            }
        }
    }

    private function sendWaitlistPromotedEmail(Booking $booking): void
    {
        $user = $booking->getMember();
        $course = $booking->getCourse();

        $email = (new TemplatedEmail())
            ->from($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com')
            ->to($user->getEmail())
            ->subject('Spot Available: ' . $course->getTitle())
            ->htmlTemplate('emails/waitlist_promoted.html.twig')
            ->context([
                'name' => $user->getName(),
                'courseTitle' => $course->getTitle(),
                'startTime' => $course->getStartTime(),
            ]);

        $this->mailer->send($email);
    }
}
