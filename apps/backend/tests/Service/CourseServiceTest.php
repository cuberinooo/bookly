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
        $trainer->method('getCompany')->willReturn($this->createMock(\App\Entity\Company::class));

        $this->courseRepository->method('findOverlappingCourses')->willReturn([]);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $courses = $this->service->createCourseSeries($data, $trainer);

        // Daily series now returns empty array as it doesn't pre-generate
        $this->assertCount(0, $courses);
    }

    public function testListCoursesDefaultRangeForMember(): void
    {
        $queryParams = [
            'memberId' => '5'
        ];

        $qb = $this->createMock(QueryBuilder::class);
        $this->courseRepository->method('createQueryBuilder')->willReturn($qb);
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $query = $this->createMock(Query::class);
        $qb->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        $serializer = $this->createMock(\Symfony\Component\Serializer\SerializerInterface::class);
        $serializer->method('serialize')->willReturn('[]');

        $service = new CourseService(
            $this->courseRepository,
            $this->seriesRepository,
            $this->entityManager,
            $this->bookingService,
            $serializer,
            $this->createMock(\App\Service\TrainingCycleService::class),
            $this->createMock(\App\Repository\UserRepository::class)
        );

        // We want to verify that the query builder receives an endDate that is ~1 year from now
        // This is hard to verify with mocks of QueryBuilder unless we capture setParameter calls.
        // But we can check if it's called.
        
        $now = new \DateTime();
        $expectedEnd = (clone $now)->setTime(0,0,0)->modify('+1 year')->setTime(23,59,59);
        
        // Use a callback to verify the endDate parameter
        $qb->expects($this->atLeastOnce())
           ->method('setParameter')
           ->with($this->logicalOr('startDate', 'endDate', 'memberId'), $this->anything())
           ->willReturnCallback(function($param, $value) use ($expectedEnd) {
               if ($param === 'endDate') {
                   $this->assertInstanceOf(\DateTime::class, $value);
                   // Check if it's within 1 minute of expected end (to account for test execution time)
                   $this->assertLessThan(60, abs($value->getTimestamp() - $expectedEnd->getTimestamp()));
               }
               return $this->createMock(QueryBuilder::class); // Need to return self-like mock
           });

        $service->listCourses($queryParams);
    }

    public function testListCoursesMergesVirtualAndReal(): void
    {
        $queryParams = [
            'startDate' => '2026-05-01T00:00:00Z',
            'endDate' => '2026-05-03T23:59:59Z'
        ];

        $company = $this->createMock(\App\Entity\Company::class);
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(1);
        $trainer->method('getName')->willReturn('John Doe');

        // Mock series: Daily at 10:00
        $series = $this->createMock(CourseSeries::class);
        $series->method('getId')->willReturn(10);
        $series->method('getTitle')->willReturn('Daily Series');
        $series->method('getFrequency')->willReturn(\App\Enum\CourseFrequency::DAILY);
        $series->method('getScheduleStartTime')->willReturn(new \DateTime('2026-04-30 10:00:00'));
        $series->method('getDurationMinutes')->willReturn(60);
        $series->method('getUser')->willReturn($trainer);
        $series->method('getCapacity')->willReturn(10);
        $series->method('isAllowTrial')->willReturn(true);
        $series->method('getCompany')->willReturn($company);

        $this->seriesRepository->method('findActiveSeries')->willReturn([$series]);

        // Mock one real course on 2026-05-01 10:00 (matching the series)
        $realCourse = $this->createMock(Course::class);
        $realCourse->method('getId')->willReturn(100);
        $realCourse->method('getSeries')->willReturn($series);
        $realCourse->method('getStartTime')->willReturn(new \DateTime('2026-05-01 10:00:00'));

        $qb = $this->createMock(QueryBuilder::class);
        $this->courseRepository->method('createQueryBuilder')->willReturn($qb);
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $query = $this->createMock(Query::class);
        $qb->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([$realCourse]);

        // Mock Serializer
        $serializer = $this->createMock(\Symfony\Component\Serializer\SerializerInterface::class);
        $serializer->method('serialize')->willReturn(json_encode([
            [
                'id' => 100,
                'startTime' => '2026-05-01T10:00:00+00:00',
                'user' => ['id' => 1]
            ]
        ]));
        
        // Use reflection to set serializer and other private dependencies if needed, 
        // but easier to just use the constructor if possible.
        // Wait, I already have the constructor.
        
        $userRepo = $this->createMock(\App\Repository\UserRepository::class);
        $userRepo->method('find')->willReturn($trainer);
        
        $cycleService = $this->createMock(\App\Service\TrainingCycleService::class);

        $service = new CourseService(
            $this->courseRepository,
            $this->seriesRepository,
            $this->entityManager,
            $this->bookingService,
            $serializer,
            $cycleService,
            $userRepo
        );

        $result = $service->listCourses($queryParams);

        // Expected: 
        // May 1: Real Course (instantiated)
        // May 2: Virtual Course
        // May 3: Virtual Course
        $this->assertCount(3, $result['data']);
        $this->assertFalse($result['data'][0]['isVirtual']);
        $this->assertTrue($result['data'][1]['isVirtual']);
        $this->assertTrue($result['data'][2]['isVirtual']);
        
        $this->assertEquals('v_10_1777716000', $result['data'][1]['id']); // Timestamp for 2026-05-02 10:00 UTC
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

        $this->entityManager->expects($this->exactly(3))->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->deleteCourseSeries($seriesId);
        $this->assertEquals(2, $count);
    }

    public function testDeleteCourseSeriesFutureOnly(): void
    {
        $seriesId = '123';
        $fromTime = new \DateTime('+1 day');

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->courseRepository->method('createQueryBuilder')->willReturn($qb);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $course1 = new Course();
        $query->method('getResult')->willReturn([$course1]);

        $series = new CourseSeries();
        $series->setActive(true);
        $this->seriesRepository->method('find')->with(123)->willReturn($series);

        $this->entityManager->expects($this->once())->method('remove')->with($course1);
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->deleteCourseSeries($seriesId, $fromTime);
        $this->assertEquals(1, $count);
        $this->assertFalse($series->isActive(), 'Series should be deactivated (not deleted) when fromTime is provided');
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
        $this->assertSame($newTrainer, $course1->getUser());
        $this->assertSame($newTrainer, $course2->getUser());
        $this->assertSame($newTrainer, $series->getUser(), 'Series template trainer should be updated');
    }
}
