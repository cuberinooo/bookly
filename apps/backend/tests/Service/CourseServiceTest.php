<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Entity\User;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;
use App\Repository\CourseSeriesRepository;
use App\Service\BookingService;
use App\Service\CourseService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class CourseServiceTest extends TestCase
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

        // Daily for 3 months (instead of 6) is roughly 90+ courses
        $this->assertGreaterThan(90, count($courses));
        $this->assertEquals('Daily Course', $courses[0]->getTitle());
        $this->assertEquals($startTime->format('Y-m-d H:i:s'), $courses[0]->getStartTime()->format('Y-m-d H:i:s'));

        $secondCourseStartTime = clone $startTime;
        $secondCourseStartTime->modify('+1 day');
        $this->assertEquals($secondCourseStartTime->format('Y-m-d H:i:s'), $courses[1]->getStartTime()->format('Y-m-d H:i:s'));

        $this->assertNotNull($courses[0]->getSeries(), 'Courses in series should have a CourseSeries reference');
    }

    public function testDeleteCourseSeries(): void
    {
        $seriesId = '123';

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

        $series = new CourseSeries();
        $this->seriesRepository->method('find')->with(123)->willReturn($series);

        $this->entityManager->expects($this->exactly(2))->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->deleteCourseSeries($seriesId);
        $this->assertEquals(2, $count);
        $this->assertFalse($series->isActive(), 'Series should be deactivated after deletion');
    }

    public function testTransferCourseSeries(): void
    {
        $seriesId = '123';
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

        $series = new CourseSeries();
        $this->seriesRepository->method('find')->with(123)->willReturn($series);

        $this->entityManager->expects($this->once())->method('flush');
        $this->bookingService->expects($this->exactly(2))->method('removeBookingIfExists');

        $count = $this->service->transferCourseSeries($seriesId, $newTrainer);

        $this->assertEquals(2, $count);
        $this->assertSame($newTrainer, $course1->getTrainer());
        $this->assertSame($newTrainer, $course2->getTrainer());
        $this->assertSame($newTrainer, $series->getTrainer(), 'Series template trainer should be updated');
    }
}
