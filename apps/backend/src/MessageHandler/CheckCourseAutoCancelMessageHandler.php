<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Course;
use App\Message\CheckCourseAutoCancelMessage;
use App\Repository\CourseRepository;
use App\Service\CourseService;
use App\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
class CheckCourseAutoCancelMessageHandler
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly CourseService $courseService,
        private readonly MessageBusInterface $messageBus,
        private readonly EmailService $emailService,
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
        $checkTime = (clone $course->getStartTime())->modify('-'.$settings->getAutoCancelHoursBefore().' hours');

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

        $confirmedBookings = array_filter($course->getBookings()->toArray(), fn ($b) => !$b->isWaitlist());

        if (count($confirmedBookings) < $settings->getAutoCancelMinParticipants()) {
            // Automatically cancel the course
            $this->courseService->cancelCourse($course, null);

            // Notify the trainer
            $this->emailService->sendNotifyTrainerOnAutoCancel($course, count($confirmedBookings));
        }
    }
}
