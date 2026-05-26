<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CycleAssignment;
use App\Entity\TrainingCategory;
use App\Entity\TrainingCycle;
use App\Entity\User;
use App\Repository\CycleAssignmentRepository;
use App\Repository\TrainingCycleRepository;
use App\Service\TrainingCycleService;
use PHPUnit\Framework\TestCase;

class TrainingCycleServiceTest extends TestCase
{
    private $cycleRepository;
    private $assignmentRepository;
    private $service;

    protected function setUp(): void
    {
        $this->cycleRepository = $this->createMock(TrainingCycleRepository::class);
        $this->assignmentRepository = $this->createMock(CycleAssignmentRepository::class);
        $this->service = new TrainingCycleService($this->cycleRepository, $this->assignmentRepository);
    }

    public function test_get_category_for_date_returns_null_if_no_cycle(): void
    {
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(1);

        $this->cycleRepository->expects($this->once())
            ->method('findActiveCycleForTrainer')
            ->with(1)
            ->willReturn(null);

        $this->cycleRepository->expects($this->once())
            ->method('findLatestCycleForTrainer')
            ->with(1)
            ->willReturn(null);

        $result = $this->service->getCategoryForDate($trainer, new \DateTime());
        $this->assertNull($result);
    }

    public function test_get_category_for_date_returns_null_if_date_before_cycle_start(): void
    {
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(1);

        $cycle = new TrainingCycle();
        $cycle->setStartDate(new \DateTime('2026-05-20'));
        $cycle->setDurationWeeks(4);

        $this->cycleRepository->method('findActiveCycleForTrainer')->willReturn($cycle);

        $result = $this->service->getCategoryForDate($trainer, new \DateTime('2026-05-19'));
        $this->assertNull($result);
    }

    public function test_get_category_for_date_calculates_correct_week_and_day(): void
    {
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(1);

        $cycle = new TrainingCycle();
        $cycle->setStartDate(new \DateTime('2026-05-18')); // Monday
        $cycle->setDurationWeeks(4);

        $category = new TrainingCategory();
        $category->setName('Strength');
        $category->setColorHex('#ff0000');

        $assignment = new CycleAssignment();
        $assignment->setWeekNumber(2);
        $assignment->setDayOfWeek(3); // Wednesday
        $assignment->setCategory($category);

        $cycle->addAssignment($assignment);

        $this->cycleRepository->method('findActiveCycleForTrainer')->willReturn($cycle);

        // Target: Wednesday of Week 2
        // Start: 2026-05-18 (Mon W1)
        // W2 Wed: 2026-05-27
        $targetDate = new \DateTime('2026-05-27');

        $result = $this->service->getCategoryForDate($trainer, $targetDate);

        $this->assertNotNull($result);
        $this->assertEquals('Strength', $result['name']);
        $this->assertEquals('#ff0000', $result['colorHex']);
    }

    public function test_get_category_for_date_handles_repetition(): void
    {
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(1);

        $cycle = new TrainingCycle();
        $cycle->setStartDate(new \DateTime('2026-05-18')); // Monday
        $cycle->setDurationWeeks(4); // 28 days

        $category = new TrainingCategory();
        $category->setName('Endurance');
        $category->setColorHex('#0000ff');

        $assignment = new CycleAssignment();
        $assignment->setWeekNumber(1);
        $assignment->setDayOfWeek(1); // Monday
        $assignment->setCategory($category);

        $cycle->addAssignment($assignment);

        $this->cycleRepository->method('findActiveCycleForTrainer')->willReturn($cycle);

        // Target: Monday 5 weeks later (Start of second iteration of the cycle)
        // 2026-05-18 + 28 days = 2026-06-15
        $targetDate = new \DateTime('2026-06-15');

        $result = $this->service->getCategoryForDate($trainer, $targetDate);

        $this->assertNotNull($result);
        $this->assertEquals('Endurance', $result['name']);
    }

    public function test_get_cycle_info_for_trainer_falls_back_to_latest(): void
    {
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(1);

        $cycle = new TrainingCycle();
        $cycle->setName('Fallback Cycle');
        $cycle->setStartDate(new \DateTime('2026-05-18'));
        $cycle->setDurationWeeks(4);

        $this->cycleRepository->method('findActiveCycleForTrainer')->willReturn(null);
        $this->cycleRepository->method('findLatestCycleForTrainer')->willReturn($cycle);

        $targetDate = new \DateTime('2026-05-18');

        $result = $this->service->getCycleInfoForTrainer($trainer, $targetDate);

        $this->assertNotNull($result);
        $this->assertEquals('Fallback Cycle', $result['name']);
    }
}
