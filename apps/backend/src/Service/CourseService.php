<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;

use App\Repository\CourseSeriesRepository;
use App\Entity\User;
use App\Enum\CourseFrequency;
use Doctrine\ORM\EntityManagerInterface;

class CourseService
{
    public function __construct(
        private CourseRepository $courseRepository,
        private CourseSeriesRepository $seriesRepository,
        private EntityManagerInterface $entityManager,
        private BookingService $bookingService
    ) {
    }

    /**
     * Creates a series of courses based on a recurrence pattern.
     *
     * @return Course[] The created courses
     */
    public function createCourseSeries(array $data, User $trainer): array
    {
        $startTime = new \DateTime($data['startTime']);
        $startTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $duration = (int) ($data['durationMinutes'] ?? 60);
        $recurrence = CourseFrequency::tryFrom($data['recurrence'] ?? '') ?? CourseFrequency::ONCE;

        if ($recurrence === CourseFrequency::ONCE) {
            $course = new Course();
            $course->setTitle($data['title']);
            $course->setDescription($data['description'] ?? '');
            $course->setCapacity((int) $data['capacity']);
            $course->setAllowTrial($data['allowTrial'] ?? true);
            $course->setStartTime($startTime);
            $course->setDurationMinutes($duration);
            $course->setEndTime((clone $startTime)->modify("+$duration minutes"));
            $course->setFrequency($recurrence);
            $course->setUser($trainer);
            $course->setCompany($trainer->getCompany());

            $this->validateSchedule($course->getStartTime(), $course->getEndTime(), null, $trainer->getId());

            $this->entityManager->persist($course);
            $this->entityManager->flush();

            return [$course];
        }

        $series = new CourseSeries();
        $series->setTitle($data['title']);
        $series->setDescription($data['description'] ?? '');
        $series->setCapacity((int) $data['capacity']);
        $series->setAllowTrial($data['allowTrial'] ?? true);
        $series->setScheduleStartTime($startTime);
        $series->setDurationMinutes($duration);
        $series->setFrequency($recurrence);
        $series->setUser($trainer);
        $series->setCompany($trainer->getCompany());

        $this->entityManager->persist($series);

        // Generate courses for the next 3 months
        $courses = $this->generateCoursesForSeries($series, $startTime, (clone $startTime)->modify('+3 months'));

        $this->entityManager->flush();
        return $courses;
    }

    public function generateCoursesForSeries(\App\Entity\CourseSeries $series, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $courses = [];
        $currentDate = clone $start;
        $trainer = $series->getUser();
        $duration = $series->getDurationMinutes();

        while ($currentDate <= $end) {
            $courseStartTime = clone $currentDate;
            $courseEndTime = (clone $courseStartTime)->modify("+$duration minutes");

            // Check if course already exists for this series at this time
            $existing = $this->courseRepository->findOneBy([
                'series' => $series,
                'startTime' => $courseStartTime
            ]);

            if (!$existing) {
                try {
                    $this->validateSchedule($courseStartTime, $courseEndTime, null, $trainer->getId());

                    $course = new Course();
                    $course->setTitle($series->getTitle());
                    $course->setDescription($series->getDescription());
                    $course->setCapacity($series->getCapacity());
                    $course->setAllowTrial($series->isAllowTrial());
                    $course->setStartTime($courseStartTime);
                    $course->setDurationMinutes($duration);
                    $course->setEndTime($courseEndTime);
                    $course->setFrequency($series->getFrequency());
                    $course->setSeries($series);
                    $course->setUser($trainer);
                    $course->setCompany($series->getCompany());

                    $this->entityManager->persist($course);
                    $courses[] = $course;
                } catch (ScheduleConflictException $e) {
                    // Skip conflicts for series generation
                }
            }

            switch ($series->getFrequency()) {
                case CourseFrequency::DAILY:
                    $currentDate->modify('+1 day');
                    break;
                case CourseFrequency::WEEKLY:
                    $currentDate->modify('+1 week');
                    break;
                case CourseFrequency::MONTHLY:
                    $currentDate->modify('+1 month');
                    break;
                case CourseFrequency::WEEKDAYS:
                    do {
                        $currentDate->modify('+1 day');
                    } while ($currentDate->format('N') >= 6);
                    break;
                default:
                    break 2;
            }
        }

        $series->setLastGeneratedDate($end);
        return $courses;
    }

    /**
     * Deletes courses in a series. If fromTime is provided, only deletes future courses.
     * Otherwise, deletes the entire series.
     */
    public function deleteCourseSeries(string $seriesId, ?\DateTimeInterface $fromTime = null): int
    {
        $qb = $this->courseRepository->createQueryBuilder('c')
            ->where('c.series = :seriesId')
            ->setParameter('seriesId', (int) $seriesId);

        if ($fromTime) {
            $qb->andWhere('c.startTime >= :now')
               ->setParameter('now', $fromTime);
        }

        $courses = $qb->getQuery()->getResult();

        foreach ($courses as $course) {
            $this->entityManager->remove($course);
        }

        // Also deactivate the series so no more courses are generated
        $series = $this->seriesRepository->find((int) $seriesId);
        if ($series) {
            $series->setActive(false);
        }

        $this->entityManager->flush();

        return count($courses);
    }

    /**
     * Updates all future courses in a series based on provided fields.
     */
    public function updateCourseSeries(string $seriesId, array $updates, ?\DateTimeInterface $fromTime = null): int
    {
        $now = $fromTime ?? new \DateTime();
        $courses = $this->courseRepository->createQueryBuilder('c')
            ->where('c.series = :seriesId')
            ->andWhere('c.startTime >= :now')
            ->setParameter('seriesId', (int) $seriesId)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $series = $this->seriesRepository->find((int) $seriesId);

        foreach ($courses as $course) {
            if (isset($updates['title'])) {
                $course->setTitle($updates['title']);
            }
            if (isset($updates['description'])) {
                $course->setDescription($updates['description']);
            }
            if (isset($updates['capacity'])) {
                $course->setCapacity($updates['capacity']);
            }
            if (isset($updates['allowTrial'])) {
                $course->setAllowTrial($updates['allowTrial']);
            }
            if (isset($updates['trainer'])) {
                $course->setUser($updates['trainer']);
                $this->bookingService->removeBookingIfExists($course, $updates['trainer']);
            }
            if (isset($updates['durationMinutes'])) {
                $course->setDurationMinutes($updates['durationMinutes']);
                $endTime = clone $course->getStartTime();
                $endTime->modify("+{$updates['durationMinutes']} minutes");
                $course->setEndTime($endTime);
            }
            if (isset($updates['startTime'])) {
                $newTime = $updates['startTime'];
                $oldStartTime = $course->getStartTime();

                $updatedStartTime = clone $oldStartTime;
                $updatedStartTime->setTime(
                    (int) $newTime->format('H'),
                    (int) $newTime->format('i'),
                    (int) $newTime->format('s')
                );

                $course->setStartTime($updatedStartTime);

                $duration = $course->getDurationMinutes();
                $updatedEndTime = clone $updatedStartTime;
                $updatedEndTime->modify("+$duration minutes");
                $course->setEndTime($updatedEndTime);
            }

            if (isset($updates['startTime']) || isset($updates['durationMinutes']) || isset($updates['trainer'])) {
                $this->validateSchedule($course->getStartTime(), $course->getEndTime(), $course->getId(), $course->getUser()->getId());
            }
        }

        if ($series) {
            if (isset($updates['title'])) $series->setTitle($updates['title']);
            if (isset($updates['description'])) $series->setDescription($updates['description']);
            if (isset($updates['capacity'])) $series->setCapacity($updates['capacity']);
            if (isset($updates['allowTrial'])) $series->setAllowTrial($updates['allowTrial']);
            if (isset($updates['trainer'])) $series->setUser($updates['trainer']);
            if (isset($updates['durationMinutes'])) $series->setDurationMinutes($updates['durationMinutes']);
            if (isset($updates['startTime'])) {
                $newTime = $updates['startTime'];
                $seriesStartTime = $series->getScheduleStartTime();
                if ($seriesStartTime) {
                    $updatedSeriesStartTime = clone $seriesStartTime;
                    $updatedSeriesStartTime->setTime(
                        (int) $newTime->format('H'),
                        (int) $newTime->format('i'),
                        (int) $newTime->format('s')
                    );
                    $series->setScheduleStartTime($updatedSeriesStartTime);
                }
            }
        }

        $this->entityManager->flush();

        return count($courses);
    }

    /**
     * Transfers all future courses in a series to a new trainer, optionally starting from a specific time.
     */
    public function transferCourseSeries(string $seriesId, User $newTrainer, ?\DateTimeInterface $fromTime = null): int
    {
        $now = $fromTime ?? new \DateTime();
        $courses = $this->courseRepository->createQueryBuilder('c')
            ->where('c.series = :seriesId')
            ->andWhere('c.startTime >= :now')
            ->setParameter('seriesId', (int) $seriesId)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($courses as $course) {
            $course->setUser($newTrainer);
            $this->bookingService->removeBookingIfExists($course, $newTrainer);
        }

        // Update the series template as well
        $series = $this->seriesRepository->find((int) $seriesId);
        if ($series) {
            $series->setUser($newTrainer);
        }

        $this->entityManager->flush();

        return count($courses);
    }

    /**
     * Checks if a course schedule overlaps with existing courses.
     *
     * @throws ScheduleConflictException if an overlap is detected
     */
    public function validateSchedule(\DateTimeInterface $startTime, \DateTimeInterface $endTime, ?int $excludeId = null, ?int $trainerId = null): void
    {
        $overlappingCourses = $this->courseRepository->findOverlappingCourses($startTime, $endTime, $excludeId, $trainerId);

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
