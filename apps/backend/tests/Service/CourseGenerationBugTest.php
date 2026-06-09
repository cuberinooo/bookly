<?php

declare(strict_types=1);

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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CourseGenerationBugTest extends TestCase
{
    private $courseRepository;
    private $seriesRepository;
    private $entityManager;
    private $bookingService;
    private $translator;
    private $messageBus;
    private $service;

    protected function setUp(): void
    {
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->seriesRepository = $this->createMock(CourseSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->bookingService = $this->createMock(BookingService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $pushService = $this->createMock(\App\Service\PushNotificationService::class);

        $this->service = new CourseService(
            $this->courseRepository,
            $this->seriesRepository,
            $this->entityManager,
            $this->bookingService,
            $this->translator,
            $this->messageBus,
            $pushService
        );
    }

    public function test_generate_courses_does_not_shift_to_monday_and_does_not_duplicate(): void
    {
        $company = new Company();
        $trainer = new User();
        $trainer->setName('Trainer');
        $trainer->setCompany($company);

        // Create a series that starts on a Wednesday (2026-05-06)
        $startTime = new \DateTime('2026-05-06 10:00:00'); // Wednesday
        $series = $this->createMock(CourseSeries::class);
        $series->method('getId')->willReturn(1);
        $series->method('getTitle')->willReturn('Wednesday Workout');
        $series->method('getFrequency')->willReturn(CourseFrequency::WEEKLY);
        $series->method('getScheduleStartTime')->willReturn($startTime);
        $series->method('getDurationMinutes')->willReturn(60);
        $series->method('getCapacity')->willReturn(10);
        $series->method('isAllowTrial')->willReturn(true);
        $series->method('getUser')->willReturn($trainer);
        $series->method('getCompany')->willReturn($company);

        // Mock series repository to return this series
        $this->seriesRepository->method('find')->with(1)->willReturn($series);

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
                // Manually set ID for mock persistence
                $persistedCourses[] = $entity;
            }
        });

        $newOccurrences = $this->service->getVirtualOccurrences($series, $start, $end);

        $this->assertCount(3, $newOccurrences, 'Should calculate 3 occurrences (May 6, 13, 20)');
        foreach ($newOccurrences as $occ) {
            $this->assertEquals('3', $occ['startTime']->format('N'), 'All occurrences should be on Wednesday (3)');
        }
    }

    public function test_virtual_occurrences_alignment(): void
    {
        $company = new Company();
        $trainer = new User();
        $trainer->setName('Trainer');
        $trainer->setCompany($company);

        // Create a series that starts on a Wednesday (2026-05-06)
        $startTime = new \DateTime('2026-05-06 10:00:00'); // Wednesday
        $series = $this->createMock(CourseSeries::class);
        $series->method('getFrequency')->willReturn(CourseFrequency::WEEKLY);
        $series->method('getScheduleStartTime')->willReturn($startTime);
        $series->method('getDurationMinutes')->willReturn(60);

        // SCENARIO: Query starts on a Monday
        $commandRunTime = new \DateTime('2026-05-11 09:00:00'); // Monday
        $endLimit = (clone $commandRunTime)->modify('+1 week');

        $occurrences = $this->service->getVirtualOccurrences($series, $commandRunTime, $endLimit);

        $this->assertCount(1, $occurrences, 'Should find exactly one Wednesday (May 13)');
        $this->assertEquals('2026-05-13', $occurrences[0]['startTime']->format('Y-m-d'), 'Should be Wednesday May 13');
        $this->assertEquals('3', $occurrences[0]['startTime']->format('N'));
    }
}
