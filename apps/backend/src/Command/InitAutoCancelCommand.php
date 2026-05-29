<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Service\CourseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-auto-cancel',
    description: 'Queues auto-cancel checks for all existing future courses',
)]
class InitAutoCancelCommand extends Command
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly CourseService $courseService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Initializing Auto-Cancel Checks');

        $now = new \DateTime();
        
        // Find all active future courses
        $courses = $this->courseRepository->createQueryBuilder('c')
            ->where('c.startTime >= :now')
            ->andWhere('c.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', \App\Enum\CourseStatus::ACTIVE)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($courses as $course) {
            /** @var Course $course */
            $settings = $course->getCompany()->getGlobalSettings();
            if ($settings && $settings->isAutoCancelEnabled()) {
                $this->courseService->dispatchAutoCancelCheck($course);
                $count++;
            }
        }

        $io->success(sprintf('Successfully queued %d future courses for auto-cancellation checks.', $count));

        return Command::SUCCESS;
    }
}
