<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\User;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;
use App\Service\CourseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CourseServiceTest extends TestCase
{
    public function testCreateCourseSeriesThrowsExceptionOnOverlap(): void
    {
        $courseRepository = $this->createMock(CourseRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new CourseService($courseRepository, $entityManager);

        $startTime = new \DateTime('2026-05-01 10:00:00');
        $data = [
            'startTime' => $startTime->format('Y-m-d H:i:s'),
            'durationMinutes' => 60,
            'title' => 'Test Course',
            'capacity' => 10,
            'recurrence' => 'once'
        ];

        // Give the trainer an ID (mocking it if necessary, but here we can just set it if we had a setter,
        // or rely on the fact that getId() will return null and we can check that,
        // or mock the User object).
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(123);

        $conflictCourse = new Course();
        $conflictCourse->setTitle('Conflict');
        $conflictCourse->setStartTime(new \DateTime('2026-05-01 10:00:00'));
        $conflictCourse->setEndTime(new \DateTime('2026-05-01 11:00:00'));

        // Mock findOverlappingCourses to return something (simulate conflict)
        $courseRepository->expects($this->once())
            ->method('findOverlappingCourses')
            ->with($this->anything(), $this->anything(), $this->isNull(), 123)
            ->willReturn([$conflictCourse]);

        $this->expectException(ScheduleConflictException::class);

        $service->createCourseSeries($data, $trainer);
    }
}
