<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\BookingWindow;
use App\Enum\NotificationType;
use App\Repository\BookingRepository;
use App\Repository\GlobalSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class BookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private GlobalSettingsRepository $settingsRepository,
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

        // Validate booking window
        $this->validateBookingWindow($course);

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
        $notification->setCourse($course);
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
        $notification->setCourse($course);
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

    private function validateBookingWindow(Course $course): void
    {
        $settings = $this->settingsRepository->get();
        $window = $settings->getBookingWindow();

        if ($window === BookingWindow::OFF) {
            return;
        }

        $deadline = $this->getBookingDeadline($window);

        if ($course->getStartTime() > $deadline) {
            $message = match ($window) {
                BookingWindow::CURRENT_WEEK => 'Bookings are only allowed for the current week.',
                BookingWindow::TWO_WEEKS => 'Bookings are only allowed for the next two weeks.',
                BookingWindow::MONTH => 'Bookings are only allowed for the next month.',
                default => 'Course is outside the booking window.',
            };
            throw new \Exception($message);
        }
    }

    private function getBookingDeadline(BookingWindow $window): \DateTime
    {
        $now = new \DateTime();
        $deadline = clone $now;

        switch ($window) {
            case BookingWindow::CURRENT_WEEK:
                // End of current week (Sunday 23:59:59)
                $day = (int) $now->format('w'); // 0 (Sunday) to 6 (Saturday)
                $daysToSunday = $day === 0 ? 0 : 7 - $day;
                $deadline->modify("+$daysToSunday days");
                $deadline->setTime(23, 59, 59);
                break;
            case BookingWindow::TWO_WEEKS:
                // 14 days from now
                $deadline->modify('+14 days');
                break;
            case BookingWindow::MONTH:
                // 1 month from now
                $deadline->modify('+1 month');
                break;
        }

        return $deadline;
    }
}
