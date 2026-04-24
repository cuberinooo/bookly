<?php

namespace App\Service;

use App\Entity\Course;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;

class CourseService
{
    public function __construct(
        private CourseRepository $courseRepository
    ) {
    }

    /**
     * Checks if a course schedule overlaps with existing courses.
     *
     * @throws ScheduleConflictException if an overlap is detected
     */
    public function validateSchedule(\DateTimeInterface $startTime, \DateTimeInterface $endTime, ?int $excludeId = null): void
    {
        $overlappingCourses = $this->courseRepository->findOverlappingCourses($startTime, $endTime, $excludeId);

        if (!empty($overlappingCourses)) {
            $conflict = $overlappingCourses[0];
            $message = sprintf(
                'Scheduling conflict: The proposed time (%s - %s) overlaps with an existing course "%s" (%s - %s).',
                $startTime->format('d.m.Y H:i'),
                $endTime->format('H:i'),
                $conflict->getTitle(),
                $conflict->getStartTime()->format('H:i'),
                $conflict->getEndTime()->format('H:i')
            );

            throw new ScheduleConflictException($message);
        }
    }
}
