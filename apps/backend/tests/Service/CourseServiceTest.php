<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseFrequency;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;
use App\Service\BookingService;
use App\Service\CourseService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class CourseServiceTest extends TestCase
{
    private $courseRepository;
    private $entityManager;
    private $bookingService;
    private $service;

    protected function setUp(): void
    {
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->bookingService = $this->createMock(BookingService::class);
        $this->service = new CourseService($this->courseRepository, $this->entityManager, $this->bookingService);
    }

    public function testCreateCourseSeriesThrowsExceptionOnOverlap(): void
    {
        $startTime = new \DateTime('2026-05-01 10:00:00');
        $data = [
            'startTime' => $startTime->format('Y-m-d H:i:s'),
            'durationMinutes' => 60,
            'title' => 'Test Course',
            'capacity' => 10,
            'recurrence' => 'once'
        ];

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(123);

        $conflictCourse = new Course();
        $conflictCourse->setTitle('Conflict');
        $conflictCourse->setStartTime(new \DateTime('2026-05-01 10:00:00'));
        $conflictCourse->setEndTime(new \DateTime('2026-05-01 11:00:00'));

        $this->courseRepository->expects($this->once())
            ->method('findOverlappingCourses')
            ->with($this->anything(), $this->anything(), $this->isNull(), 123)
            ->willReturn([$conflictCourse]);

        $this->expectException(ScheduleConflictException::class);
        
        $this->service->createCourseSeries($data, $trainer);
    }

    public function testCreateCourseSeriesDaily(): void
    {
        $startTime = new \DateTime('2026-05-01 10:00:00');
        $data = [
            'startTime' => $startTime->format('Y-m-d H:i:s'),
            'durationMinutes' => 60,
            'title' => 'Daily Course',
            'capacity' => 10,
            'recurrence' => 'daily'
        ];

        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(123);

        $this->courseRepository->method('findOverlappingCourses')->willReturn([]);

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $courses = $this->service->createCourseSeries($data, $trainer);

        // Daily for 6 months is roughly 180+ courses
        $this->assertGreaterThan(180, count($courses));
        $this->assertEquals('Daily Course', $courses[0]->getTitle());
        $this->assertEquals($startTime->format('Y-m-d H:i:s'), $courses[0]->getStartTime()->format('Y-m-d H:i:s'));
        
        $secondCourseStartTime = clone $startTime;
        $secondCourseStartTime->modify('+1 day');
        $this->assertEquals($secondCourseStartTime->format('Y-m-d H:i:s'), $courses[1]->getStartTime()->format('Y-m-d H:i:s'));
    }

    public function testDeleteCourseSeries(): void
    {
        $seriesId = 'test-series';
        
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->courseRepository->method('createQueryBuilder')->willReturn($qb);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $course1 = new Course();
        $course2 = new Course();
        $query->method('getResult')->willReturn([$course1, $course2]);

        $this->entityManager->expects($this->exactly(2))->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->deleteCourseSeries($seriesId);
        $this->assertEquals(2, $count);
    }

    public function testTransferCourseSeries(): void
    {
        $seriesId = 'test-series';
        $newTrainer = new User();
        
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->courseRepository->method('createQueryBuilder')->willReturn($qb);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $course1 = new Course();
        $course2 = new Course();
        $query->method('getResult')->willReturn([$course1, $course2]);

        $this->entityManager->expects($this->once())->method('flush');
        $this->bookingService->expects($this->exactly(2))->method('removeBookingIfExists');

        $count = $this->service->transferCourseSeries($seriesId, $newTrainer);
        
        $this->assertEquals(2, $count);
        $this->assertSame($newTrainer, $course1->getTrainer());
        $this->assertSame($newTrainer, $course2->getTrainer());
    }
}
