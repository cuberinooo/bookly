<?php

namespace App\Command;

use App\Repository\CourseSeriesRepository;
use App\Service\CourseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:courses:expand-series',
    description: 'Generates course instances for active recurring series for the next 3 months.',
)]
class ExpandCourseSeriesCommand extends Command
{
    public function __construct(
        private CourseSeriesRepository $seriesRepository,
        private CourseService $courseService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $activeSeries = $this->seriesRepository->findActiveSeries();

        $io->note(sprintf('Found %d active recurring series.', count($activeSeries)));

        $endLimit = new \DateTime('+3 months');
        $totalCreated = 0;

        foreach ($activeSeries as $series) {
            $io->text(sprintf('Processing series: %s (ID: %d)', $series->getTitle(), $series->getId()));
            
            // Start from the last generated date or from now
            $startDate = $series->getLastGeneratedDate() ?? new \DateTime();
            
            // Safety: if lastGeneratedDate is in the past, start from now to avoid re-scanning old dates
            if ($startDate < new \DateTime()) {
                $startDate = new \DateTime();
            }

            $newCourses = $this->courseService->generateCoursesForSeries($series, $startDate, $endLimit);
            $totalCreated += count($newCourses);
            
            if (count($newCourses) > 0) {
                $io->text(sprintf('  - Created %d new instances.', count($newCourses)));
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Completed. Total new course instances created: %d', $totalCreated));

        return Command::SUCCESS;
    }
}
