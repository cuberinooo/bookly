<?php

namespace App\Service;

use App\Entity\Course;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;

use App\Entity\User;
use App\Enum\CourseFrequency;
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
        $recurrence = CourseFrequency::tryFrom($data['recurrence'] ?? '') ?? CourseFrequency::ONCE;
        
        $seriesId = $recurrence !== CourseFrequency::ONCE ? bin2hex(random_bytes(8)) : null;

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
            $course->setFrequency($recurrence);
            $course->setSeriesId($seriesId);
            $course->setTrainer($trainer);

            $this->entityManager->persist($course);
            $courses[] = $course;

            if ($recurrence === CourseFrequency::ONCE) {
                break;
            }

            next_iteration:
            switch ($recurrence) {
                case CourseFrequency::DAILY:
                    $currentDate->modify('+1 day');
                    break;
                case CourseFrequency::WEEKLY:
                    $currentDate->modify('+1 week');
                    break;
                case CourseFrequency::WEEKDAYS:
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
     * Deletes all future courses in a series.
     */
    public function deleteCourseSeries(string $seriesId): int
    {
        $now = new \DateTime();
        $courses = $this->courseRepository->createQueryBuilder('c')
            ->where('c.seriesId = :seriesId')
            ->andWhere('c.startTime > :now')
            ->setParameter('seriesId', $seriesId)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($courses as $course) {
            $this->entityManager->remove($course);
        }

        $this->entityManager->flush();

        return count($courses);
    }

    /**
     * Transfers all future courses in a series to a new trainer.
     */
    public function transferCourseSeries(string $seriesId, User $newTrainer): int
    {
        $now = new \DateTime();
        $courses = $this->courseRepository->createQueryBuilder('c')
            ->where('c.seriesId = :seriesId')
            ->andWhere('c.startTime >= :now')
            ->setParameter('seriesId', $seriesId)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($courses as $course) {
            $course->setTrainer($newTrainer);
        }

        $this->entityManager->flush();

        return count($courses);
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
