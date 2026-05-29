<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\BookingWindow;
use App\Repository\BookingRepository;
use App\Repository\GlobalSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private GlobalSettingsRepository $settingsRepository,
        private MailerInterface $mailer,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Books a course for a user.
     *
     * @throws \Exception if already booked
     *
     * @return array [Booking $booking, bool $isWaitlist]
     */
    public function book(Course $course, User $user): array
    {
        // Check if the course is already done
        if ($course->getEndTime() < new \DateTime()) {
            throw new \Exception($this->translator->trans('error.cannot_book_finished'));
        }

        // Check if the course is postponed
        if (\App\Enum\CourseStatus::POSTPONED === $course->getStatus()) {
            throw new \Exception($this->translator->trans('error.course_postponed_no_book'));
        }

        // Validate booking window
        $this->validateBookingWindow($course);

        // Check if trial members are allowed
        if (in_array('ROLE_TRIAL', $user->getRoles(), true) && !$course->isAllowTrial()) {
            throw new \Exception($this->translator->trans('error.not_for_trial'));
        }

        // Check if the user is the trainer of the course
        if ($course->getUser()->getId() === $user->getId()) {
            throw new \Exception($this->translator->trans('error.trainer_cannot_book_own'));
        }

        // Check if already booked
        $existingBooking = $this->bookingRepository->findOneBy(['user' => $user, 'course' => $course]);
        if ($existingBooking) {
            throw new \Exception($this->translator->trans('error.already_booked'));
        }

        // Trial limit check
        if (in_array('ROLE_TRIAL', $user->getRoles(), true)) {
            $globalSettings = $user->getCompany()->getGlobalSettings();
            $limit = $globalSettings ? $globalSettings->getTrialBookingLimit() : 0;

            if ($limit > 0) {
                $totalBookings = $this->bookingRepository->count(['user' => $user]);
                if ($totalBookings >= $limit) {
                    throw new \Exception($this->translator->trans('error.trial_limit_reached', ['%limit%' => $limit]));
                }
            }
        }

        // Waitlist logic: if count of confirmed bookings >= capacity, it's a waitlist booking
        $confirmedBookingsCount = $this->bookingRepository->count(['course' => $course, 'isWaitlist' => false]);
        $isWaitlist = $confirmedBookingsCount >= $course->getCapacity();

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setCourse($course);
        $booking->setWaitlist($isWaitlist);
        $booking->setCompany($course->getCompany());

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        // Send confirmation email if not on waitlist
        if (!$isWaitlist) {
            $this->sendBookingConfirmationEmail($booking);
        }

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
            throw new \Exception($this->translator->trans('error.cannot_cancel_finished'));
        }

        $booking = $this->bookingRepository->findOneBy(['user' => $user, 'course' => $course]);
        if (!$booking) {
            throw new \Exception($this->translator->trans('error.booking_not_found'));
        }

        $wasWaitlist = $booking->isWaitlist();

        // Send cancellation email if it was a confirmed booking
        if (!$wasWaitlist) {
            $this->sendBookingCancellationEmail($booking);
        }

        $this->entityManager->remove($booking);
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

        if (!$wasWaitlist) {
            $this->sendBookingCancellationEmail($booking);
        }

        $this->entityManager->remove($booking);
        $this->entityManager->flush();

        if (!$wasWaitlist && $course) {
            $this->processWaitlist($course);
        }
    }

    /**
     * Toggles the attendance status of a booking.
     */
    public function toggleAttendance(Booking $booking): void
    {
        $booking->setAttended(!$booking->isAttended());
        $this->entityManager->flush();
    }

    /**
     * Removes a booking for a specific user and course if it exists.
     */
    public function removeBookingIfExists(Course $course, User $user): void
    {
        $booking = $this->bookingRepository->findOneBy(['user' => $user, 'course' => $course]);
        if ($booking) {
            $wasWaitlist = $booking->isWaitlist();
            if (!$wasWaitlist) {
                $this->sendBookingCancellationEmail($booking);
            }
            $this->entityManager->remove($booking);
            $this->entityManager->flush();

            if (!$wasWaitlist) {
                $this->processWaitlist($course);
            }
        }
    }

    private function processWaitlist(Course $course): void
    {
        // Never promote from waitlist if the course has already finished
        if ($course->getEndTime() < new \DateTime()) {
            return;
        }

        // Check if there is space now
        $confirmedBookingsCount = $this->bookingRepository->count(['course' => $course, 'isWaitlist' => false]);

        if ($confirmedBookingsCount < $course->getCapacity()) {
            $nextInWaitlist = $this->bookingRepository->findNextInWaitlist($course);

            if ($nextInWaitlist) {
                $nextInWaitlist->setWaitlist(false);
                $this->entityManager->flush();

                // Send both waitlist promotion and formal confirmation
                $this->sendWaitlistPromotedEmail($nextInWaitlist);
                $this->sendBookingConfirmationEmail($nextInWaitlist);

                // Recursively check if there's more space (e.g. if capacity was increased)
                $this->processWaitlist($course);
            }
        }
    }

    private function sendBookingConfirmationEmail(Booking $booking): void
    {
        $user = $booking->getUser();
        $course = $booking->getCourse();
        $company = $course->getCompany();
        $uid = sprintf('booking_%d_%d', $course->getId(), $user->getId());

        $email = (new TemplatedEmail())
            ->from($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com')
            ->to($user->getEmail())
            ->subject('Booking Confirmed: '.$course->getTitle())
            ->htmlTemplate('emails/booking_confirmation.html.twig')
            ->context([
                'userName' => $user->getName(),
                'courseName' => $course->getTitle(),
                'startTime' => $course->getStartTime(),
                'endTime' => $course->getEndTime(),
                'location' => $company->getName(),
                'uid' => $uid,
                'siteName' => $company->getName(),
            ]);

        $icsContent = $this->generateIcsContent($booking);
        $email->attach($icsContent, 'booking.ics', 'text/calendar');

        $this->mailer->send($email);
    }

    private function sendBookingCancellationEmail(Booking $booking): void
    {
        $user = $booking->getUser();
        $course = $booking->getCourse();
        $company = $course->getCompany();
        $uid = sprintf('booking_%d_%d', $course->getId(), $user->getId());

        $email = (new TemplatedEmail())
            ->from($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com')
            ->to($user->getEmail())
            ->subject('Booking Cancelled: '.$course->getTitle())
            ->htmlTemplate('emails/booking_cancellation.html.twig')
            ->context([
                'userName' => $user->getName(),
                'courseName' => $course->getTitle(),
                'startTime' => $course->getStartTime(),
                'location' => $company->getName(),
                'uid' => $uid,
                'siteName' => $company->getName(),
            ]);

        $icsContent = $this->generateIcsContent($booking, true);
        $email->attach($icsContent, 'cancel_booking.ics', 'text/calendar');

        $this->mailer->send($email);
    }

    private function generateIcsContent(Booking $booking, bool $isCancellation = false): string
    {
        $user = $booking->getUser();
        $course = $booking->getCourse();
        $company = $course->getCompany();
        $uid = sprintf('booking_%d_%d', $course->getId(), $user->getId());

        $dtStart = $course->getStartTime()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
        $dtEnd = $course->getEndTime()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
        $dtStamp = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Ymd\\THis\\Z');

        $method = $isCancellation ? 'CANCEL' : 'PUBLISH';
        $status = $isCancellation ? 'CANCELLED' : 'CONFIRMED';
        $summary = ($isCancellation ? 'CANCELLED: ' : '').$course->getTitle();
        $sequence = $isCancellation ? '1' : '0';

        $ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Phoenix Booking//Course Booking//EN',
            'METHOD:'.$method,
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$dtStamp,
            'DTSTART:'.$dtStart,
            'DTEND:'.$dtEnd,
            'SUMMARY:'.$summary,
            'LOCATION:'.$company->getName(),
            'DESCRIPTION:'.($isCancellation ? 'Booking Cancellation' : 'Booking Confirmation').' for '.$user->getName(),
            'STATUS:'.$status,
            'SEQUENCE:'.$sequence,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $ics);
    }

    private function sendWaitlistPromotedEmail(Booking $booking): void
    {
        $user = $booking->getUser();
        $course = $booking->getCourse();

        $email = (new TemplatedEmail())
            ->from($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com')
            ->to($user->getEmail())
            ->subject('Spot Available: '.$course->getTitle())
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
        $settings = $this->settingsRepository->find($course->getUser()->getCompany()->getGlobalSettings()->getId());
        $window = $settings->getBookingWindow();

        if (BookingWindow::OFF === $window) {
            return;
        }

        $deadline = $this->getBookingDeadline($window);

        if ($course->getStartTime() > $deadline) {
            $message = match ($window) {
                BookingWindow::CURRENT_WEEK => $this->translator->trans('error.booking_window_current_week'),
                BookingWindow::TWO_WEEKS => $this->translator->trans('error.booking_window_two_weeks'),
                BookingWindow::MONTH => $this->translator->trans('error.booking_window_month'),
                default => $this->translator->trans('error.booking_window_outside'),
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
                $daysToSunday = 0 === $day ? 0 : 7 - $day;
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
