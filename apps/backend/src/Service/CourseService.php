<?php

namespace App\Service;

use App\Entity\Course;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class CourseService
{
    public function __construct(
        private CourseRepository $courseRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Creates a series of courses based on a recurrence pattern.
     *
     * @return Course[] The created courses
     */
    public function createCourseSeries(array $data, User $trainer): array
    {
        $courses = [];
        $startTime = new \DateTime($data['startTime']);
        $duration = (int) ($data['durationMinutes'] ?? 60);
        $recurrence = $data['recurrence'] ?? 'NONE';
        
        $limitDate = (clone $startTime)->modify('+6 months');
        $currentDate = clone $startTime;

        while ($currentDate <= $limitDate) {
            $courseStartTime = clone $currentDate;
            $courseEndTime = (clone $courseStartTime)->modify("+$duration minutes");

            try {
                $this->validateSchedule($courseStartTime, $courseEndTime);
            } catch (ScheduleConflictException $e) {
                // If it's a series, we might want to skip conflicts or abort.
                // For now, let's abort if the first one conflicts, but maybe skip subsequent ones?
                // The user said "symfony will do the rest", usually implying it should be robust.
                // Let's collect successful ones.
                if ($currentDate === $startTime) {
                    throw $e;
                }
                goto next_iteration;
            }

            $course = new Course();
            $course->setTitle($data['title']);
            $course->setDescription($data['description'] ?? '');
            $course->setCapacity((int) $data['capacity']);
            $course->setStartTime($courseStartTime);
            $course->setDurationMinutes($duration);
            $course->setEndTime($courseEndTime);
            $course->setTrainer($trainer);

            $this->entityManager->persist($course);
            $courses[] = $course;

            if ($recurrence === 'NONE') {
                break;
            }

            next_iteration:
            switch ($recurrence) {
                case 'DAILY':
                    $currentDate->modify('+1 day');
                    break;
                case 'WEEKLY':
                    $currentDate->modify('+1 week');
                    break;
                case 'WEEKDAYS':
                    do {
                        $currentDate->modify('+1 day');
                    } while ($currentDate->format('N') >= 6); // Skip Saturday (6) and Sunday (7)
                    break;
                default:
                    break 2; // Break the while loop
            }
        }

        $this->entityManager->flush();
        return $courses;
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
