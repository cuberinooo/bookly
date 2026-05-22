<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Exercise;
use App\Entity\User;
use App\Entity\UserWorkoutRecord;
use App\Repository\UserRepository;
use App\Repository\UserWorkoutRecordRepository;
use Doctrine\ORM\EntityManagerInterface;

class LeaderboardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserWorkoutRecordRepository $recordRepository
    ) {
    }

    public function getMonthlyStats(): array
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        $endOfMonth = new \DateTime('last day of this month 23:59:59');

        // 1. Get total attendance for the current month per user
        $qb = $this->em->createQueryBuilder();
        $attendanceData = $qb->select('u.id as userId', 'COUNT(b.id) as attendanceCount')
            ->from(User::class, 'u')
            ->leftJoin('u.bookings', 'b')
            ->leftJoin('b.course', 'c')
            ->andWhere('b.isWaitlist = :isWaitlist')
            ->andWhere('b.attended = :attended')
            ->andWhere('u.isPublic = :isPublic')
            ->andWhere('c.startTime >= :start')
            ->andWhere('c.startTime <= :end')
            ->setParameter('isWaitlist', false)
            ->setParameter('attended', true)
            ->setParameter('isPublic', true)
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->groupBy('u.id')
            ->getQuery()
            ->getArrayResult();

        $attendanceMap = [];
        foreach ($attendanceData as $row) {
            $attendanceMap[$row['userId']] = (int)$row['attendanceCount'];
        }

        // 2. Calculate streaks (consecutive weeks attended)
        $qb = $this->em->createQueryBuilder();
        $weeksData = $qb->select('DISTINCT u.id as userId', 'c.startTime')
            ->from(User::class, 'u')
            ->join('u.bookings', 'b')
            ->join('b.course', 'c')
            ->andWhere('b.isWaitlist = :isWaitlist')
            ->andWhere('b.attended = :attended')
            ->andWhere('u.isPublic = :isPublic')
            ->andWhere('c.startTime <= :now')
            ->setParameter('isWaitlist', false)
            ->setParameter('attended', true)
            ->setParameter('isPublic', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('u.id', 'ASC')
            ->addOrderBy('c.startTime', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $userWeeks = [];
        foreach ($weeksData as $row) {
            $yw = (int)$row['startTime']->format('oW');
            if (!isset($userWeeks[$row['userId']]) || !in_array($yw, $userWeeks[$row['userId']])) {
                $userWeeks[$row['userId']][] = $yw;
            }
        }

        $currentYearWeek = (int)(new \DateTime())->format('oW');
        $streaks = [];
        foreach ($userWeeks as $userId => $weeks) {
            $streak = 0;
            $checkWeek = $currentYearWeek;

            if (!in_array($checkWeek, $weeks)) {
                $dt = new \DateTime();
                $dt->modify('-1 week');
                $checkWeek = (int)$dt->format('oW');
            }

            if (in_array($checkWeek, $weeks)) {
                $dt = new \DateTime();
                if ($checkWeek !== $currentYearWeek) {
                    $dt->modify('-1 week');
                }

                while (in_array((int)$dt->format('oW'), $weeks)) {
                    $streak++;
                    $dt->modify('-1 week');
                }
            }
            $streaks[$userId] = $streak;
        }

        // 3. Build response
        $users = $this->userRepository->findBy(['isActive' => true, 'isPublic' => true]);
        $data = [];
        foreach ($users as $user) {
            $userId = $user->getId();
            $data[] = [
                'id' => $userId,
                'name' => $user->getName(),
                'profilePicture' => $user->getProfilePicture(),
                'attendanceCount' => $attendanceMap[$userId] ?? 0,
                'streak' => $streaks[$userId] ?? 0,
                'gender' => $user->getGender()?->value,
            ];
        }

        usort($data, function ($a, $b) {
            if ($a['attendanceCount'] === $b['attendanceCount']) {
                return $b['streak'] <=> $a['streak'];
            }
            return $b['attendanceCount'] <=> $a['attendanceCount'];
        });

        return $data;
    }

    public function getWorkoutRecords(): array
    {
        $topRecords = $this->recordRepository->findTopRecordsByExercise();
        $exercises = $this->em->getRepository(Exercise::class)->findAll();
        
        $grouped = [];
        foreach ($exercises as $ex) {
            $grouped[$ex->getName()] = [
                'unit' => $ex->getUnit() ?? 'kg',
                'male' => [],
                'female' => [],
                'other' => []
            ];
        }

        foreach ($topRecords as $row) {
            $exName = $row['exerciseName'];
            $gender = $row['gender'];
            if ($gender instanceof \App\Enum\Gender) {
                $gender = $gender->value;
            }
            $gender = $gender ?? 'other';

            if (!isset($grouped[$exName])) {
                $grouped[$exName] = [
                    'unit' => 'kg',
                    'male' => [],
                    'female' => [],
                    'other' => []
                ];
            }

            $record = [
                'userId' => $row['userId'],
                'name' => $row['userName'],
                'profilePicture' => $row['profilePicture'],
                'weightValue' => (float)$row['maxWeight'],
                'dateAchieved' => $row['dateAchieved'],
            ];

            if ($gender === 'male' || $gender === 'female' || $gender === 'other') {
                $grouped[$exName][$gender][] = $record;
            }
        }

        return $grouped;
    }

    public function submitRecord(User $user, string $exerciseName, float $weightValue): UserWorkoutRecord
    {
        $exercise = $this->em->getRepository(Exercise::class)->findOneBy(['name' => $exerciseName]);
        if (!$exercise) {
            throw new \InvalidArgumentException('Invalid exercise');
        }

        $record = new UserWorkoutRecord();
        $record->setUser($user);
        $record->setExerciseName($exerciseName);
        $record->setWeightValue($weightValue);
        $record->setDateAchieved(new \DateTime());

        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }
}
