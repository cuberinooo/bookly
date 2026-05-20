<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;

use App\Repository\CourseSeriesRepository;
use App\Repository\UserRepository;
use App\Service\TrainingCycleService;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\User;
use App\Enum\CourseFrequency;
use Doctrine\ORM\EntityManagerInterface;

class CourseService
{
    public function __construct(
        private CourseRepository $courseRepository,
        private CourseSeriesRepository $seriesRepository,
        private EntityManagerInterface $entityManager,
        private BookingService $bookingService,
        private ?SerializerInterface $serializer = null,
        private ?TrainingCycleService $cycleService = null,
        private ?UserRepository $userRepository = null
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
        $trainer = $series->getUser();
        $duration = $series->getDurationMinutes();
        $frequency = $series->getFrequency();
        
        // Calculate the first valid occurrence date >= $start based on $series->getScheduleStartTime()
        $scheduleStart = $series->getScheduleStartTime();
        $currentDate = clone $scheduleStart;
        
        // Advance $currentDate until it is >= $start
        while ($currentDate < $start) {
            switch ($frequency) {
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

        // If fromTime is NOT provided, it means we want to delete the WHOLE series
        $series = $this->seriesRepository->find((int) $seriesId);
        if ($series) {
            if ($fromTime) {
                // Future only: deactivate so no more are generated
                $series->setActive(false);
            } else {
                // Whole series: delete the template entity
                $this->entityManager->remove($series);
            }
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

        // Filter out postponed courses from conflict detection
        $overlappingCourses = array_filter($overlappingCourses, fn(Course $c) => $c->getStatus() === \App\Enum\CourseStatus::ACTIVE);

        if (!empty($overlappingCourses)) {
            $conflict = reset($overlappingCourses);
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

    public function postponeCourse(Course $course, User $trainer): void
    {
        if ($course->getStatus() === \App\Enum\CourseStatus::POSTPONED) {
            throw new \LogicException('Course is already postponed.');
        }

        $course->setStatus(\App\Enum\CourseStatus::POSTPONED);
        $course->setPostponedBy($trainer);

        // Unbook all members
        $bookings = $course->getBookings();
        foreach ($bookings as $booking) {
            $this->bookingService->removeBookingIfExists($course, $booking->getUser());
        }

        $this->entityManager->flush();
    }

    /**
     * Finds and formats courses with training cycle category enrichment.
     */
    public function listCourses(array $queryParams): array
    {
        $startDateStr = $queryParams['startDate'] ?? null;
        $endDateStr = $queryParams['endDate'] ?? null;
        $serverTz = new \DateTimeZone(date_default_timezone_get());
        $startDate = $startDateStr ? (new \DateTime($startDateStr))->setTimezone($serverTz) : null;
        $endDate = $endDateStr ? (new \DateTime($endDateStr))->setTimezone($serverTz) : null;
        $futureOnly = (bool)($queryParams['futureOnly'] ?? false);
        $trainerId = isset($queryParams['trainerId']) && $queryParams['trainerId'] !== '' ? (int)$queryParams['trainerId'] : null;
        $memberId = isset($queryParams['memberId']) && $queryParams['memberId'] !== '' ? (int)$queryParams['memberId'] : null;
        $all = (bool)($queryParams['all'] ?? false);

        if ($all) {
            $qb = $this->courseRepository->createQueryBuilder('c');
            if ($futureOnly && !$startDate) {
                $qb->andWhere('c.endTime >= :now')
                   ->setParameter('now', new \DateTime());
            } elseif ($startDate) {
                $qb->andWhere('c.endTime >= :startDate')
                   ->setParameter('startDate', $startDate);
            }

            if ($endDate) {
                $qb->andWhere('c.startTime <= :endDate')
                   ->setParameter('endDate', $endDate);
            }

            if ($trainerId) {
                $qb->andWhere('c.user = :trainerId')
                   ->setParameter('trainerId', $trainerId);
            }

            if ($memberId) {
                $qb->join('c.bookings', 'b')
                   ->andWhere('b.user = :memberId')
                   ->setParameter('memberId', $memberId);
            }

            $courses = $qb->orderBy('c.startTime', 'ASC')
               ->getQuery()
               ->getResult();

            $data = json_decode($this->serializer->serialize($courses, 'json', ['groups' => 'course:read']), true);

            foreach ($data as &$courseData) {
                $course = null;
                foreach ($courses as $c) {
                    if ($c->getId() === $courseData['id']) {
                        $course = $c;
                        break;
                    }
                }
                if ($course) {
                    $cycleCategory = $this->cycleService->getCategoryForDate($course->getUser(), $course->getStartTime());
                    if ($cycleCategory) {
                        $courseData['cycleCategory'] = $cycleCategory;
                    }
                }
            }

            $trainer = $trainerId ? $this->userRepository->find($trainerId) : null;
            $cycleInfo = null;
            if ($trainer) {
                $cycleInfo = $this->cycleService->getCycleInfoForTrainer($trainer, $startDate ?? new \DateTime());
            } else {
                $cycleInfo = $this->cycleService->getCycleInfoForCompany($startDate ?? new \DateTime());
            }
            return [
                'data' => $data,
                'cycle' => $cycleInfo
            ];
        }

        $page = (int)($queryParams['page'] ?? 1);
        $limit = (int)($queryParams['limit'] ?? 20);

        $paginatedResults = $this->courseRepository->findPaginated($page, $limit, $startDate, $endDate, $futureOnly, $trainerId, $memberId);

        $courses = $paginatedResults['data'];
        unset($paginatedResults['data']);

        $enrichedData = json_decode($this->serializer->serialize($courses, 'json', ['groups' => 'course:read']), true);

        foreach ($enrichedData as &$courseData) {
            $course = null;
            foreach ($courses as $c) {
                if ($c->getId() === $courseData['id']) {
                    $course = $c;
                    break;
                }
            }
            if ($course) {
                $cycleCategory = $this->cycleService->getCategoryForDate($course->getUser(), $course->getStartTime());
                if ($cycleCategory) {
                    $courseData['cycleCategory'] = $cycleCategory;
                }
            }
        }

        $trainer = $trainerId ? $this->userRepository->find($trainerId) : null;
        $cycleInfo = null;
        if ($trainer) {
            $cycleInfo = $this->cycleService->getCycleInfoForTrainer($trainer, $startDate ?? new \DateTime());
        } else {
            $cycleInfo = $this->cycleService->getCycleInfoForCompany($startDate ?? new \DateTime());
        }
        return [
            'data' => $enrichedData,
            'meta' => $paginatedResults,
            'cycle' => $cycleInfo
        ];
    }

    /**
     * Updates a single course and validates its schedule.
     */
    public function updateSingleCourse(Course $course, array $data, ?User $newTrainer = null): void
    {
        if ($newTrainer) {
            $course->setUser($newTrainer);
            $this->bookingService->removeBookingIfExists($course, $newTrainer);
        }

        if (isset($data['startTime']) || isset($data['durationMinutes'])) {
            $serverTz = new \DateTimeZone(date_default_timezone_get());
            $startTime = isset($data['startTime']) ? (new \DateTime($data['startTime']))->setTimezone($serverTz) : $course->getStartTime();
            $duration = (int) (isset($data['durationMinutes']) ? $data['durationMinutes'] : ($course->getDurationMinutes() ?? 60));

            $endTime = clone $startTime;
            $endTime->modify("+$duration minutes");

            $trainerId = $newTrainer ? $newTrainer->getId() : $course->getUser()->getId();
            $this->validateSchedule($startTime, $endTime, $course->getId(), $trainerId);

            $course->setStartTime($startTime);
            $course->setDurationMinutes($duration);
            $course->setEndTime($endTime);
        }

        if (isset($data['title'])) {
            $course->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $course->setDescription($data['description']);
        }
        if (isset($data['capacity'])) {
            $course->setCapacity((int) $data['capacity']);
        }
        if (isset($data['allowTrial'])) {
            $course->setAllowTrial((bool) $data['allowTrial']);
        }

        $this->entityManager->flush();
    }
}
