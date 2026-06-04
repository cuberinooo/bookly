<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\Entity\User;
use App\Message\SendCompanyEmailMessage;
use Aws\S3\S3ClientInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;

class EmailService
{
    public function __construct(
        private MessageBusInterface $bus,
        private BodyRendererInterface $bodyRenderer,
        private S3ClientInterface $s3Client,
        private string $s3Bucket
    ) {
    }

    public function sendVerificationEmail(User $user, bool $isAdminCreation = false, ?string $temporaryPassword = null): void
    {
        $company = $user->getCompany();
        $siteName = $company ? $company->getName() : 'Bookly';

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject(sprintf('Welcome to %s - Your Account is Ready', $siteName))
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'name' => $user->getName(),
                'siteName' => $siteName,
                'url' => $this->getVerificationUrl($user),
                'loginUrl' => $this->getLoginUrl(),
                'isAdminCreation' => $isAdminCreation,
                'temporaryPassword' => $temporaryPassword,
                'isVerified' => $user->isVerified(),
            ]);

        $email->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $siteName));
        $this->send($company->getId(), $email);
    }

    public function sendMembershipWelcomeEmail(User $user): void
    {
        $company = $user->getCompany();
        if (!$company) {
            return;
        }
        $settings = $company->getAdminSettings();

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $company->getName()))
            ->to($user->getEmail());

        $markdown = $settings->getMembershipWelcomeMailMarkdown() ?? '';
        $siteName = $company->getName();
        $placeholders = [
            '{user_name}' => $user->getName(),
            '{company_name}' => $siteName,
        ];

        $content = str_replace(array_keys($placeholders), array_values($placeholders), $markdown);

        $email->subject(sprintf('Welcome to the community at %s!', $siteName))
            ->htmlTemplate('emails/company_welcome.html.twig')
            ->context([
                'content' => $content,
                'name' => $user->getName(),
                'siteName' => $siteName,
                'loginUrl' => $this->getLoginUrl(),
            ]);

        // Attach files
        $attachments = $settings->getMembershipWelcomeMailAttachments() ?? [];
        $this->attachFiles($email, $attachments);

        $this->send($company->getId(), $email);
    }

    public function sendCompanySpecificWelcomeEmail(User $user): void
    {
        $company = $user->getCompany();
        if (!$company) {
            return;
        }
        $settings = $company->getAdminSettings();

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $company->getName()))
            ->to($user->getEmail());

        $markdown = $settings->getWelcomeMailMarkdown() ?? '';
        $siteName = $company->getName();
        $placeholders = [
            '{user_name}' => $user->getName(),
            '{company_name}' => $siteName,
        ];

        $content = str_replace(array_keys($placeholders), array_values($placeholders), $markdown);

        $email->subject(sprintf('Welcome to %s!', $siteName))
            ->htmlTemplate('emails/company_welcome.html.twig')
            ->context([
                'content' => $content,
                'name' => $user->getName(),
                'siteName' => $siteName,
                'loginUrl' => $this->getLoginUrl(),
            ]);

        // Attach files
        $attachments = $settings->getWelcomeMailAttachments() ?? [];
        $this->attachFiles($email, $attachments);

        $this->send($company->getId(), $email);
    }

    public function sendPasswordResetEmail(User $user, string $temporaryPassword): void
    {
        $company = $user->getCompany();
        $siteName = $company ? $company->getName() : 'Bookly';

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $siteName))
            ->to($user->getEmail())
            ->subject('Account Security: Password Reset by Administrator')
            ->htmlTemplate('emails/admin_password_reset.html.twig')
            ->context([
                'name' => $user->getName(),
                'siteName' => $siteName,
                'temporaryPassword' => $temporaryPassword,
                'siteUrl' => $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200',
            ]);

        if ($company) {
            $this->send($company->getId(), $email);
        }
    }

    public function sendForgotPasswordEmail(User $user, string $token): void
    {
        $company = $user->getCompany();
        $siteName = $company ? $company->getName() : 'Bookly';
        $frontendUrl = $_ENV['FRONTEND_URL'];
        $resetUrl = $frontendUrl.'/reset-password?token='.$token;

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $siteName))
            ->to($user->getEmail())
            ->subject(sprintf('Reset your %s password', $siteName))
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'name' => $user->getName(),
                'url' => $resetUrl,
            ]);

        if ($company) {
            $this->send($company->getId(), $email);
        }
    }

    public function sendCourseReminderEmail(Course $course): void
    {
        $trainer = $course->getUser();
        $company = $course->getCompany();
        if (!$company) {
            return;
        }

        $participants = [];
        foreach ($course->getBookings() as $booking) {
            if (!$booking->isWaitlist()) {
                $participants[] = [
                    'name' => $booking->getUser()->getName(),
                    'email' => $booking->getUser()->getEmail(),
                ];
            }
        }

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $company->getName()))
            ->to($trainer->getEmail())
            ->subject('Course Reminder: '.$course->getTitle())
            ->htmlTemplate('emails/course_reminder.html.twig')
            ->context([
                'course' => $course,
                'participants' => $participants,
            ]);

        $this->send($company->getId(), $email);
    }

    public function sendBookingConfirmationEmail(Booking $booking): void
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

        $this->send($company->getId(), $email);
    }

    public function sendBookingCancellationEmail(Booking $booking): void
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
                'isAutoCancelled' => $course->isAutoCancelled(),
            ]);

        $icsContent = $this->generateIcsContent($booking, true);
        $email->attach($icsContent, 'cancel_booking.ics', 'text/calendar');

        $this->send($company->getId(), $email);
    }

    public function sendWaitlistPromotedEmail(Booking $booking): void
    {
        $user = $booking->getUser();
        $company = $user->getCompany();
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

        $this->send($company->getId(), $email);
    }

    public function sendAdminNotificationEmail(User $user, array $admins): void
    {
        $company = $user->getCompany();
        $adminEmails = array_map(fn (User $admin) => $admin->getEmail(), $admins);

        if (empty($adminEmails)) {
            // Fallback to a configured admin email if no admin users found in DB
            $fallbackAdmin = $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com';
            $adminEmails = [$fallbackAdmin];
        }

        $email = (new TemplatedEmail())
            ->from($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com')
            ->to(...$adminEmails)
            ->subject('New User Registration: '.$user->getName())
            ->htmlTemplate('emails/admin_new_user.html.twig')
            ->context([
                'name' => $user->getName(),
                'userEmail' => $user->getEmail(),
                'siteName' => $user->getCompany()->getName(),
                'role' => implode(', ', array_map(fn ($r) => str_replace('ROLE_', '', $r), $user->getRoles())),
            ]);

        $this->send($company->getId(), $email);
    }

    public function sendNotificationEmailOnMeetup(User $user, Meetup $meetup): void
    {
        $company = $user->getCompany();
        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $meetup->getCompany()->getName()))
            ->to($user->getEmail())
            ->subject(sprintf('New Meetup: %s', $meetup->getTitle()))
            ->htmlTemplate('emails/meetup_invitation.html.twig')
            ->context([
                'meetup' => $meetup,
                'user' => $user,
                'siteName' => $meetup->getCompany()->getName(),
                'loginUrl' => $this->getLoginUrl(),
            ]);

        $this->send($company->getId(), $email);
    }

    public function sendParticipantsOfCancellation(Meetup $meetup, MeetupRsvp $rsvp): void
    {
        $company = $meetup->getCompany();

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $meetup->getCompany()->getName()))
            ->to($rsvp->getUser()->getEmail())
            ->subject(sprintf('Meetup Cancelled: %s', $meetup->getTitle()))
            ->htmlTemplate('emails/meetup_cancellation.html.twig')
            ->context([
                'meetup' => $meetup,
                'user' => $rsvp->getUser(),
                'siteName' => $meetup->getCompany()->getName(),
                'loginUrl' => $this->getLoginUrl(),
            ]);

        $this->send($company->getId(), $email);
    }

    public function sendParticipantsOfConfirmation(Meetup $meetup, MeetupRsvp $rsvp): void
    {
        $company = $meetup->getCompany();

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $meetup->getCompany()->getName()))
            ->to($rsvp->getUser()->getEmail())
            ->subject(sprintf('Meetup Confirmed: %s', $meetup->getTitle()))
            ->htmlTemplate('emails/meetup_confirmation.html.twig')
            ->context([
                'meetup' => $meetup,
                'user' => $rsvp->getUser(),
                'siteName' => $meetup->getCompany()->getName(),
                'loginUrl' => $this->getLoginUrl(),
            ]);

        $this->send($company->getId(), $email);
    }

    public function sendNotifyTrainerOnAutoCancel(Course $course, int $participantCount): void
    {
        $trainer = $course->getUser();
        if (!$trainer) {
            return;
        }

        $siteName = $course->getCompany()->getName();
        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $siteName))
            ->to($trainer->getEmail())
            ->subject(sprintf('[%s] Automatic Cancellation: %s', $siteName, $course->getTitle()))
            ->htmlTemplate('emails/auto_cancel_notification.html.twig')
            ->context([
                'course' => $course,
                'participantCount' => $participantCount,
                'minParticipants' => $course->getCompany()->getGlobalSettings()->getAutoCancelMinParticipants(),
                'siteName' => $siteName,
                'loginUrl' => $this->getLoginUrl()
            ]);

        $this->send($course->getCompany()->getId(), $email);
    }

    private function send(int $companyId, TemplatedEmail $email): void
    {
        $this->bodyRenderer->render($email);
        $this->bus->dispatch(new SendCompanyEmailMessage($companyId, $email));
    }

    public function sendPriceChangeNotification(User $user, float $newPrice): void
    {
        $company = $user->getCompany();
        $siteName = $company ? $company->getName() : 'Phoenix Athletics';

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $siteName))
            ->to($user->getEmail())
            ->subject(sprintf('Important: Membership Price Update for %s', $siteName))
            ->htmlTemplate('emails/price_update.html.twig')
            ->context([
                'name' => $user->getName(),
                'siteName' => $siteName,
                'newPrice' => number_format($newPrice, 2, ',', '.') . ' €',
                'loginUrl' => $this->getLoginUrl(),
            ]);

        $this->mailer->send($email);
    }

    private function attachFiles(TemplatedEmail $email, array $attachments): void
    {
        foreach ($attachments as $att) {
            try {
                $result = $this->s3Client->getObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $att['path'],
                ]);
                $email->attach($result['Body']->getContents(), $att['name']);
            } catch (\Exception $e) {
                // Log error but continue sending email without this attachment
            }
        }
    }

    private function getVerificationUrl(User $user): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';

        return $frontendUrl.'/verify-email?token='.$user->getVerificationToken();
    }

    private function getLoginUrl(): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';

        return $frontendUrl.'/login';
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
}
