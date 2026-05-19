<?php

namespace App\Service;

use App\Entity\TrainingCycle;
use App\Entity\TrainingCategory;
use App\Entity\User;
use App\Repository\TrainingCycleRepository;
use App\Repository\CycleAssignmentRepository;

class TrainingCycleService
{
    public function __construct(
        private TrainingCycleRepository $cycleRepository,
        private CycleAssignmentRepository $assignmentRepository
    ) {}

    public function getCategoryForDate(User $trainer, \DateTimeInterface $date): ?array
    {
        $activeCycle = $this->cycleRepository->findActiveCycleForTrainer($trainer->getId());
        
        if (!$activeCycle) {
            return null;
        }

        $startDate = $activeCycle->getStartDate();
        
        // Clone and reset time for accurate day difference
        $start = \DateTime::createFromInterface($startDate)->setTime(0, 0, 0);
        $target = \DateTime::createFromInterface($date)->setTime(0, 0, 0);
        
        $diff = $target->diff($start);
        $daysElapsed = (int)$diff->format("%r%a");
        
        // Note: diff format %r%a returns negative if target > start, 
        // wait, actually $target->diff($start) returns positive if target < start.
        // Let's use target - start.
        $daysElapsed = (int)$start->diff($target)->format("%r%a");

        if ($daysElapsed < 0) {
            return null; // Date is before cycle start
        }

        $totalDaysInCycle = $activeCycle->getDurationWeeks() * 7;
        $dayInCycle = $daysElapsed % $totalDaysInCycle;
        
        $weekNumber = (int)floor($dayInCycle / 7) + 1;
        $dayOfWeek = (int)$target->format('N'); // 1 (Mon) to 7 (Sun)

        foreach ($activeCycle->getAssignments() as $assignment) {
            if ($assignment->getWeekNumber() === $weekNumber && $assignment->getDayOfWeek() === $dayOfWeek) {
                $category = $assignment->getCategory();
                return [
                    'categoryName' => $category->getName(),
                    'categoryColor' => $category->getColorHex()
                ];
            }
        }

        return null;
    }

    public function getCycleInfoForTrainer(User $trainer, \DateTimeInterface $date): ?array
    {
        $activeCycle = $this->cycleRepository->findActiveCycleForTrainer($trainer->getId());
        
        if (!$activeCycle) {
            return null;
        }

        $startDate = $activeCycle->getStartDate();
        $start = \DateTime::createFromInterface($startDate)->setTime(0, 0, 0);
        $target = \DateTime::createFromInterface($date)->setTime(0, 0, 0);
        
        $daysElapsed = (int)$start->diff($target)->format("%r%a");

        if ($daysElapsed < 0) {
            return null;
        }

        $totalDaysInCycle = $activeCycle->getDurationWeeks() * 7;
        $dayInCycle = $daysElapsed % $totalDaysInCycle;
        $weekNumber = (int)floor($dayInCycle / 7) + 1;

        return [
            'name' => $activeCycle->getName(),
            'currentWeek' => $weekNumber,
            'totalWeeks' => $activeCycle->getDurationWeeks()
        ];
    }
}
