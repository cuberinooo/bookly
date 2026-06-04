<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CourseRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:course-reminder',
    description: 'Sends email reminders to trainers before courses start',
)]
class CourseReminderCommand extends Command
{
    public function __construct(
        private CourseRepository $courseRepository,
        private EntityManagerInterface $entityManager,
        private EmailService $emailService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTime();

        // Find all future courses where reminder hasn't been sent
        $qb = $this->courseRepository->createQueryBuilder('c')
            ->join('c.user', 'u')
            ->where('c.reminderSent = :false')
            ->andWhere('c.startTime > :now')
            ->setParameter('false', false)
            ->setParameter('now', $now);

        $courses = $qb->getQuery()->getResult();
        $sentCount = 0;

        foreach ($courses as $course) {
            $trainer = $course->getUser();
            $notificationHours = $trainer->getCourseStartNotificationHours();
            $notificationMinutes = $trainer->getCourseStartNotificationMinutes();

            // Total notification lead time in minutes
            $leadTimeMinutes = ($notificationHours * 60) + $notificationMinutes;

            if ($leadTimeMinutes <= 0) {
                continue; // Trainer disabled notifications
            }

            $reminderTime = (clone $course->getStartTime())->modify("-{$leadTimeMinutes} minutes");

            if ($now >= $reminderTime) {
                $this->emailService->sendCourseReminderEmail($course);
                $course->setReminderSent(true);
                ++$sentCount;
            }
        }

        $this->entityManager->flush();

        if ($sentCount > 0) {
            $io->success("Sent $sentCount reminders.");
        } else {
            $io->info('No reminders to send.');
        }

        return Command::SUCCESS;
    }
}
