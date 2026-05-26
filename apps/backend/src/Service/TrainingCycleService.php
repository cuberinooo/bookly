<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\CycleAssignmentRepository;
use App\Repository\TrainingCycleRepository;

class TrainingCycleService
{
    public function __construct(
        private TrainingCycleRepository $cycleRepository,
        private CycleAssignmentRepository $assignmentRepository
    ) {
    }

    public function getCategoryForDate(User $trainer, \DateTimeInterface $date): ?array
    {
        $cycle = $this->cycleRepository->findActiveCycle()
            ?? $this->cycleRepository->findLatestCycle();

        if (!$cycle) {
            return null;
        }

        $start = \DateTime::createFromInterface($cycle->getStartDate())->setTime(0, 0, 0);
        $target = \DateTime::createFromInterface($date)->setTime(0, 0, 0);

        $daysElapsed = (int) $start->diff($target)->format('%r%a');

        if ($daysElapsed < 0) {
            return null; // Date is before cycle start
        }

        $totalWeeks = $cycle->getDurationWeeks();
        $weekInCycle = (int) floor($daysElapsed / 7) % $totalWeeks + 1;
        $dayOfWeek = (int) $target->format('N'); // 1 (Mon) to 7 (Sun)

        foreach ($cycle->getAssignments() as $assignment) {
            if ($assignment->getWeekNumber() === $weekInCycle && $assignment->getDayOfWeek() === $dayOfWeek) {
                $category = $assignment->getCategory();

                return [
                    'name' => $category->getName(),
                    'colorHex' => $category->getColorHex(),
                    'description' => $category->getDescription(),
                ];
            }
        }

        return null;
    }

    public function getCycleInfoForTrainer(User $trainer, \DateTimeInterface $date): ?array
    {
        return $this->getCycleInfoForCompany($date);
    }

    public function getCycleInfoForCompany(\DateTimeInterface $date): ?array
    {
        $cycle = $this->cycleRepository->findActiveCycle()
            ?? $this->cycleRepository->findLatestCycle();

        if (!$cycle) {
            return null;
        }

        $start = \DateTime::createFromInterface($cycle->getStartDate())->setTime(0, 0, 0);
        $target = \DateTime::createFromInterface($date)->setTime(0, 0, 0);

        $daysElapsed = (int) $start->diff($target)->format('%r%a');

        $totalWeeks = $cycle->getDurationWeeks();

        // If it's before the start date, we still return it as Week 1
        $currentWeek = 1;
        if ($daysElapsed >= 0) {
            $currentWeek = (int) floor($daysElapsed / 7) % $totalWeeks + 1;
        }

        return [
            'name' => $cycle->getName(),
            'currentWeek' => $currentWeek,
            'totalWeeks' => $totalWeeks,
        ];
    }
}
