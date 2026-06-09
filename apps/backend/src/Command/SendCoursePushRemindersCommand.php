<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Booking;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-course-push-reminders',
    description: 'Sends push notification reminders to athletes 1 hour before their courses start',
)]
class SendCoursePushRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushNotificationService $pushService,
        private readonly \Symfony\Contracts\Translation\TranslatorInterface $translator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTime();
        $limitTime = (clone $now)->modify('+70 minutes'); // Look ahead 1 hour and 10 minutes

        // Find confirmed bookings starting soon that haven't received a push reminder
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('b')
            ->from(Booking::class, 'b')
            ->join('b.course', 'c')
            ->where('b.pushReminderSent = :false')
            ->andWhere('b.isWaitlist = :false')
            ->andWhere('c.startTime > :now')
            ->andWhere('c.startTime <= :limitTime')
            ->andWhere('c.status = :activeStatus')
            ->setParameter('false', false)
            ->setParameter('now', $now)
            ->setParameter('limitTime', $limitTime)
            ->setParameter('activeStatus', \App\Enum\CourseStatus::ACTIVE);

        /** @var Booking[] $bookings */
        $bookings = $qb->getQuery()->getResult();
        $sentCount = 0;

        foreach ($bookings as $booking) {
            $user = $booking->getUser();
            $course = $booking->getCourse();

            if ($user && $course) {
                try {
                    $this->pushService->sendNotification(
                        $user,
                        $this->translator->trans('push.course_reminder.title'),
                        $this->translator->trans('push.course_reminder.body', [
                            '%title%' => $course->getTitle(),
                            '%time%' => $course->getStartTime()->format('H:i')
                        ]),
                        '/courses'
                    );
                    $booking->setPushReminderSent(true);
                    ++$sentCount;
                } catch (\Exception $e) {
                    $io->error(sprintf('Failed sending reminder to %s: %s', $user->getEmail(), $e->getMessage()));
                }
            }
        }

        $this->entityManager->flush();

        if ($sentCount > 0) {
            $io->success(sprintf('Sent %d course push reminders.', $sentCount));
        } else {
            $io->info('No course push reminders to send.');
        }

        return Command::SUCCESS;
    }
}
