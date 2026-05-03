<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Entity\User;
use App\Enum\CourseFrequency;
use App\Repository\CourseRepository;
use App\Repository\CourseSeriesRepository;
use App\Service\BookingService;
use App\Service\CourseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CourseGenerationBugTest extends TestCase
{
    private $courseRepository;
    private $seriesRepository;
    private $entityManager;
    private $bookingService;
    private $service;

    protected function setUp(): void
    {
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->seriesRepository = $this->createMock(CourseSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->bookingService = $this->createMock(BookingService::class);
        $this->service = new CourseService(
            $this->courseRepository,
            $this->seriesRepository,
            $this->entityManager,
            $this->bookingService
        );
    }

    public function testGenerateCoursesDoesNotShiftToMondayAndDoesNotDuplicate(): void
    {
        $company = new Company();
        $trainer = new User();
        $trainer->setCompany($company);

        // Create a series that starts on a Wednesday (2026-05-06)
        $startTime = new \DateTime('2026-05-06 10:00:00'); // Wednesday
        $series = new CourseSeries();
        $series->setTitle('Wednesday Workout');
        $series->setFrequency(CourseFrequency::WEEKLY);
        $series->setScheduleStartTime($startTime);
        $series->setDurationMinutes(60);
        $series->setCapacity(10);
        $series->setUser($trainer);
        $series->setCompany($company);

        // Mock overlap check to always return empty
        $this->courseRepository->method('findOverlappingCourses')->willReturn([]);

        // SCENARIO 1: First generation
        // Last generated date is null
        $start = clone $startTime;
        $end = (clone $start)->modify('+2 weeks');

        // Capture persisted courses
        $persistedCourses = [];
        $this->entityManager->method('persist')->willReturnCallback(function ($entity) use (&$persistedCourses) {
            if ($entity instanceof Course) {
                $persistedCourses[] = $entity;
            }
        });

        $newCourses = $this->service->generateCoursesForSeries($series, $start, $end);

        $this->assertCount(3, $newCourses, 'Should create 3 courses (May 6, 13, 20)');
        foreach ($newCourses as $c) {
            $this->assertEquals('3', $c->getStartTime()->format('N'), 'All courses should be on Wednesday (3)');
        }

        // SCENARIO 2: Duplication check
        // Simulate that May 6 already exists in DB
        $this->courseRepository->method('findOneBy')->willReturnCallback(function ($criteria) use ($newCourses) {
            foreach ($newCourses as $existing) {
                if ($existing->getStartTime()->getTimestamp() === $criteria['startTime']->getTimestamp()) {
                    return $existing;
                }
            }
            return null;
        });

        $persistedCourses = [];
        $againCourses = $this->service->generateCoursesForSeries($series, $start, $end);
        $this->assertCount(0, $againCourses, 'Should not create duplicates when re-running for same period');

        // SCENARIO 3: The "Monday" Bug / LastGeneratedDate alignment
        // If we start from the 'end' date of previous generation
        $newStart = clone $end; // This is a Wednesday (2026-05-20)
        $newEnd = (clone $newStart)->modify('+1 week');

        $persistedCourses = [];
        $thirdRunCourses = $this->service->generateCoursesForSeries($series, $newStart, $newEnd);
        
        // It should find May 20 exists, and only create May 27
        $this->assertCount(1, $thirdRunCourses, 'Should create 1 new course for the extended period');
        $this->assertEquals('2026-05-27', $thirdRunCourses[0]->getStartTime()->format('Y-m-d'));
        $this->assertEquals('3', $thirdRunCourses[0]->getStartTime()->format('N'), 'Should still be Wednesday');
    }

    public function testCommandWorkaroundStartFromNowMightCauseMondayBug(): void
    {
        $company = new Company();
        $trainer = new User();
        $trainer->setCompany($company);

        // Create a series that starts on a Wednesday (2026-05-06)
        $startTime = new \DateTime('2026-05-06 10:00:00'); // Wednesday
        $series = new CourseSeries();
        $series->setTitle('Wednesday Workout');
        $series->setFrequency(CourseFrequency::WEEKLY);
        $series->setScheduleStartTime($startTime);
        $series->setDurationMinutes(60);
        $series->setCapacity(10);
        $series->setUser($trainer);
        $series->setCompany($company);

        // Mock overlap check
        $this->courseRepository->method('findOverlappingCourses')->willReturn([]);

        // SCENARIO: Command runs on a Monday, and uses new DateTime() as start date
        // Suppose it's Monday 2026-05-11
        $commandRunTime = new \DateTime('2026-05-11 09:00:00'); // Monday
        $endLimit = (clone $commandRunTime)->modify('+1 week');

        $newCourses = $this->service->generateCoursesForSeries($series, $commandRunTime, $endLimit);

        $this->assertCount(1, $newCourses, 'Should find exactly one Wednesday (May 13)');
        $this->assertEquals('2026-05-13', $newCourses[0]->getStartTime()->format('Y-m-d'), 'Should be Wednesday May 13');
        $this->assertEquals('3', $newCourses[0]->getStartTime()->format('N'));
    }
}
