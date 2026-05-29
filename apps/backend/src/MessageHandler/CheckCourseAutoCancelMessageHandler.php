<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Course;
use App\Message\CheckCourseAutoCancelMessage;
use App\Repository\CourseRepository;
use App\Service\CourseService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class CheckCourseAutoCancelMessageHandler
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly CourseService $courseService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function __invoke(CheckCourseAutoCancelMessage $message): void
    {
        $course = $this->courseRepository->find($message->getCourseId());

        if (!$course || \App\Enum\CourseStatus::ACTIVE !== $course->getStatus()) {
            return;
        }

        $company = $course->getCompany();
        $settings = $company->getGlobalSettings();

        if (!$settings || !$settings->isAutoCancelEnabled()) {
            return;
        }

        $now = new \DateTime();
        $checkTime = (clone $course->getStartTime())->modify('-' . $settings->getAutoCancelHoursBefore() . ' hours');

        // Self-healing: If we are checking too early (e.g. course was rescheduled to later)
        if ($now < $checkTime) {
            $delay = ($checkTime->getTimestamp() - $now->getTimestamp()) * 1000;
            if ($delay > 0) {
                $this->messageBus->dispatch($message, [new DelayStamp($delay)]);
            }
            return;
        }

        // Only check courses that are in the future but within the cancellation window
        // (if the course already started, we don't auto-cancel it retroactively)
        if ($now > $course->getStartTime()) {
            return;
        }

        $confirmedBookings = array_filter($course->getBookings()->toArray(), fn($b) => !$b->isWaitlist());
        
        if (count($confirmedBookings) < $settings->getAutoCancelMinParticipants()) {
            // Automatically cancel the course
            $this->courseService->postponeCourse($course, null);
            
            // Notify the trainer
            $this->notifyTrainer($course, count($confirmedBookings));
        }
    }

    private function notifyTrainer(Course $course, int $participantCount): void
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

        $this->mailer->send($email);
    }

    private function getLoginUrl(): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';

        return $frontendUrl.'/login';
    }
}
