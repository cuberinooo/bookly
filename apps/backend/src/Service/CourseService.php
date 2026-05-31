<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Entity\User;
use App\Enum\CourseFrequency;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;
use App\Repository\CourseSeriesRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CourseService
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly CourseSeriesRepository $seriesRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingService $bookingService,
        private readonly TranslatorInterface $translator,
        private readonly MessageBusInterface $messageBus,
        private readonly ?SerializerInterface $serializer = null,
        private readonly ?TrainingCycleService $cycleService = null,
        private readonly ?UserRepository $userRepository = null
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

        if (CourseFrequency::ONCE === $recurrence) {
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

            $this->dispatchAutoCancelCheck($course);

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
        $this->entityManager->flush();

        // No longer pre-generating courses for 3 months here.
        // The calendar will generate virtual occurrences on-the-fly.
        return [];
    }

    public function dispatchAutoCancelCheck(Course $course): void
    {
        $settings = $course->getCompany()->getGlobalSettings();
        if (!$settings || !$settings->isAutoCancelEnabled()) {
            return;
        }

        $now = new \DateTime();
        $checkTime = (clone $course->getStartTime())->modify('-' . $settings->getAutoCancelHoursBefore() . ' hours');
        
        $delaySeconds = $checkTime->getTimestamp() - $now->getTimestamp();
        $delay = max(0, $delaySeconds) * 1000;
        
        $this->messageBus->dispatch(new \App\Message\CheckCourseAutoCancelMessage($course->getId()), [new DelayStamp($delay)]);
    }

    public function queueFutureAutoCancelChecks(\App\Entity\Company $company): void
    {
        $now = new \DateTime();
        $courses = $this->courseRepository->createQueryBuilder('c')
            ->where('c.company = :company')
            ->andWhere('c.startTime >= :now')
            ->andWhere('c.status = :status')
            ->setParameter('company', $company)
            ->setParameter('now', $now)
            ->setParameter('status', \App\Enum\CourseStatus::ACTIVE)
            ->getQuery()
            ->getResult();

        foreach ($courses as $course) {
            $this->dispatchAutoCancelCheck($course);
        }
    }

    /**
     * Calculates occurrences for a series within a date range.
     *
     * @return array Array of occurrence objects with startTime and endTime
     */
    public function getVirtualOccurrences(CourseSeries $series, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $occurrences = [];
        $duration = $series->getDurationMinutes();
        $frequency = $series->getFrequency();

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

            $occurrences[] = [
                'startTime' => $courseStartTime,
                'endTime' => $courseEndTime,
            ];

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

        return $occurrences;
    }

    /**
     * Atomically instantiates a virtual course from a series and startTime.
     */
    public function instantiateVirtualCourse(int $seriesId, \DateTimeInterface $startTime): Course
    {
        // 1. Check if it already exists
        $existing = $this->courseRepository->findOneBy([
            'series' => $seriesId,
            'startTime' => $startTime,
        ]);

        if ($existing) {
            return $existing;
        }

        // 2. Load series
        $series = $this->seriesRepository->find($seriesId);
        if (!$series) {
            throw new \InvalidArgumentException('Series not found');
        }

        // 3. Create new course
        $course = new Course();
        $course->setTitle($series->getTitle());
        $course->setDescription($series->getDescription());
        $course->setCapacity($series->getCapacity());
        $course->setAllowTrial($series->isAllowTrial());
        $course->setStartTime($startTime);
        $course->setDurationMinutes($series->getDurationMinutes());
        $course->setEndTime((clone $startTime)->modify("+{$series->getDurationMinutes()} minutes"));
        $course->setFrequency($series->getFrequency());
        $course->setSeries($series);
        $course->setUser($series->getUser());
        $course->setCompany($series->getCompany());

        // Validate schedule (skip if already exists in DB but not loaded yet)
        $this->validateSchedule($course->getStartTime(), $course->getEndTime(), null, $course->getUser()->getId());

        try {
            $this->entityManager->persist($course);
            $this->entityManager->flush();

            $this->dispatchAutoCancelCheck($course);

            return $course;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Concurrent request created it first
            $this->entityManager->detach($course);

            return $this->courseRepository->findOneBy([
                'series' => $seriesId,
                'startTime' => $startTime,
            ]);
        }
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
            $this->unbookAll($course);
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
        $series = $this->seriesRepository->find((int) $seriesId);
        if (!$series) {
            return 0;
        }

        $now = $fromTime ?? new \DateTime();
        
        // 1. Instantiate future occurrences for the next 3 months to make them "real" for the update
        $threeMonthsLater = (clone $now)->modify('+3 months');
        $occurrences = $this->getVirtualOccurrences($series, $now, $threeMonthsLater);
        
        foreach ($occurrences as $occ) {
            $this->instantiateVirtualCourse($series->getId(), $occ['startTime']);
        }

        // 2. Fetch all real courses for this series that are in the future
        $courses = $this->courseRepository->createQueryBuilder('c')
            ->where('c.series = :seriesId')
            ->andWhere('c.startTime >= :now')
            ->setParameter('seriesId', (int) $seriesId)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

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
            
            if (isset($updates['startTime'])) {
                $this->dispatchAutoCancelCheck($course);
            }
        }

        if ($series) {
            if (isset($updates['title'])) {
                $series->setTitle($updates['title']);
            }
            if (isset($updates['description'])) {
                $series->setDescription($updates['description']);
            }
            if (isset($updates['capacity'])) {
                $series->setCapacity($updates['capacity']);
            }
            if (isset($updates['allowTrial'])) {
                $series->setAllowTrial($updates['allowTrial']);
            }
            if (isset($updates['trainer'])) {
                $series->setUser($updates['trainer']);
            }
            if (isset($updates['durationMinutes'])) {
                $series->setDurationMinutes($updates['durationMinutes']);
            }
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

        // Filter out cancelled courses from conflict detection
        $overlappingCourses = array_filter($overlappingCourses, fn (Course $c) => \App\Enum\CourseStatus::ACTIVE === $c->getStatus());

        if (!empty($overlappingCourses)) {
            $conflict = reset($overlappingCourses);
            $message = $this->translator->trans('error.schedule_conflict', [
                '%start%' => $startTime->format('d.m.Y H:i'),
                '%end%' => $endTime->format('H:i'),
                '%title%' => $conflict->getTitle(),
                '%conflict_start%' => $conflict->getStartTime()->format('H:i'),
                '%conflict_end%' => $conflict->getEndTime()->format('H:i'),
            ]);

            throw new ScheduleConflictException($message);
        }
    }

    public function cancelCourse(Course $course, ?User $trainer = null): void
    {
        if (\App\Enum\CourseStatus::CANCELLED === $course->getStatus()) {
            throw new \LogicException($this->translator->trans('error.course_already_cancelled'));
        }

        $course->setStatus(\App\Enum\CourseStatus::CANCELLED);
        $course->setCancelledBy($trainer);

        $this->unbookAll($course);

        $this->entityManager->flush();
    }

    public function deleteCourse(Course $course): void
    {
        $seriesId = $course->getSeriesId();

        if ($seriesId) {
            // Soft delete for series courses
            $course->setStatus(\App\Enum\CourseStatus::DELETED);
            $this->unbookAll($course);
        } else {
            // Hard delete for non-series courses
            $this->entityManager->remove($course);
        }

        $this->entityManager->flush();
    }

    private function unbookAll(Course $course): void
    {
        $bookings = $course->getBookings();
        foreach ($bookings as $booking) {
            $this->bookingService->removeBookingIfExists($course, $booking->getUser());
        }
    }

    public function listCourses(array $queryParams): array
    {
        $startDateStr = $queryParams['startDate'] ?? null;
        $endDateStr = $queryParams['endDate'] ?? null;
        $memberId = isset($queryParams['memberId']) && '' !== $queryParams['memberId'] ? (int) $queryParams['memberId'] : null;

        $serverTz = new \DateTimeZone(date_default_timezone_get());
        $startDate = $startDateStr ? (new \DateTime($startDateStr))->setTimezone($serverTz) : (new \DateTime())->setTime(0, 0, 0);

        // If memberId is provided, we default to a much larger range (1 year) to ensure all bookings are shown
        $defaultDuration = $memberId ? '+1 year' : '+7 days';
        $endDate = $endDateStr ? (new \DateTime($endDateStr))->setTimezone($serverTz) : (clone $startDate)->modify($defaultDuration)->setTime(23, 59, 59);

        $futureOnly = (bool) ($queryParams['futureOnly'] ?? false);
        $trainerId = isset($queryParams['trainerId']) && '' !== $queryParams['trainerId'] ? (int) $queryParams['trainerId'] : null;
        $page = (int) ($queryParams['page'] ?? 1);
        $limit = (int) ($queryParams['limit'] ?? 20);
        $all = (bool) ($queryParams['all'] ?? false);

        // 1. Fetch real courses (all in range for merging)
        $qb = $this->courseRepository->createQueryBuilder('c');
        $qb->andWhere('c.startTime <= :endDate')
           ->andWhere('c.endTime >= :startDate')
           ->setParameter('startDate', $startDate)
           ->setParameter('endDate', $endDate);

        // If memberId is provided, we want to see ALL their bookings regardless of course status
        // (so they see history, cancelled ones, etc.), but NOT deleted ones.
        // If NO memberId, we show ACTIVE and CANCELLED courses (for calendar/management)
        if (!$memberId) {
            $qb->andWhere('c.status IN (:statuses)')
               ->setParameter('statuses', [
                   \App\Enum\CourseStatus::ACTIVE,
                   \App\Enum\CourseStatus::CANCELLED,
               ]);
        } else {
            $qb->andWhere('c.status != :deletedStatus')
               ->setParameter('deletedStatus', \App\Enum\CourseStatus::DELETED);
        }

        if ($futureOnly) {
            $qb->andWhere('c.endTime >= :now')
               ->setParameter('now', new \DateTime());
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

        $realCourses = $qb->orderBy('c.startTime', 'ASC')
            ->getQuery()
            ->getResult();


        // 2. Fetch all real courses (including non-active) to prevent virtual generation for them
        $allRealCoursesInRange = $this->courseRepository->createQueryBuilder('c')
            ->andWhere('c.startTime <= :endDate')
            ->andWhere('c.endTime >= :startDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        // 3. Fetch active series and generate virtual occurrences
        $virtualCourses = [];
        if (!$memberId) {
            $activeSeries = $this->seriesRepository->findActiveSeries();
            foreach ($activeSeries as $series) {
                if ($trainerId && $series->getUser()->getId() !== $trainerId) {
                    continue;
                }

                $occurrences = $this->getVirtualOccurrences($series, $startDate, $endDate);
                foreach ($occurrences as $occ) {
                    // Check if ANY real course already exists for this occurrence
                    $exists = false;
                    foreach ($allRealCoursesInRange as $real) {
                        if ($real->getSeries() && $real->getSeries()->getId() === $series->getId()
                            && $real->getStartTime()->getTimestamp() === $occ['startTime']->getTimestamp()) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        $virtualCourses[] = [
                            'id' => 'v_'.$series->getId().'_'.$occ['startTime']->getTimestamp(),
                            'seriesId' => (string) $series->getId(),
                            'title' => $series->getTitle(),
                            'description' => $series->getDescription(),
                            'capacity' => $series->getCapacity(),
                            'allowTrial' => $series->isAllowTrial(),
                            'startTime' => $occ['startTime']->format(\DateTime::ATOM),
                            'endTime' => $occ['endTime']->format(\DateTime::ATOM),
                            'durationMinutes' => $series->getDurationMinutes(),
                            'frequency' => $series->getFrequency()->value,
                            'status' => 'active',
                            'user' => [
                                'id' => $series->getUser()->getId(),
                                'name' => $series->getUser()->getName(),
                            ],
                            'bookings' => [],
                            'isVirtual' => true,
                        ];
                    }
                }
            }
        }


        // 3. Serialize real courses
        $data = json_decode($this->serializer->serialize($realCourses, 'json', ['groups' => 'course:read']), true);
        foreach ($data as &$courseData) {
            $courseData['isVirtual'] = false;
        }

        // 4. Merge and Sort
        $merged = array_merge($data, $virtualCourses);
        usort($merged, function ($a, $b) {
            return strcmp($a['startTime'], $b['startTime']);
        });

        // 5. Pagination (only if not 'all')
        $totalItems = count($merged);
        if (!$all) {
            $merged = array_slice($merged, ($page - 1) * $limit, $limit);
        }

        // 6. Enrich with cycle info
        foreach ($merged as &$item) {
            $tId = $item['user']['id'];
            $sTime = new \DateTime($item['startTime']);
            $trainer = $this->userRepository->find($tId);
            if ($trainer) {
                $cycleCategory = $this->cycleService->getCategoryForDate($trainer, $sTime);
                if ($cycleCategory) {
                    $item['cycleCategory'] = $cycleCategory;
                }
            }
        }

        $trainer = $trainerId ? $this->userRepository->find($trainerId) : null;
        $cycleInfo = null;
        if ($trainer) {
            $cycleInfo = $this->cycleService->getCycleInfoForTrainer($trainer, $startDate);
        } else {
            $cycleInfo = $this->cycleService->getCycleInfoForCompany($startDate);
        }

        $response = [
            'data' => $merged,
            'cycle' => $cycleInfo,
        ];

        if (!$all) {
            $response['meta'] = [
                'totalItems' => $totalItems,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($totalItems / $limit),
            ];
        }

        return $response;
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
            $duration = (int) ($data['durationMinutes'] ?? ($course->getDurationMinutes() ?? 60));

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

        if (isset($data['startTime'])) {
            $this->dispatchAutoCancelCheck($course);
        }
    }
}
