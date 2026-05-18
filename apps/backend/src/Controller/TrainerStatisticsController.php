<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/trainer/statistics')]
#[IsGranted('ROLE_TRAINER')]
class TrainerStatisticsController extends AbstractController
{
    #[Route('', name: 'trainer_statistics', methods: ['GET'])]
    public function getStatistics(CourseRepository $courseRepository, BookingRepository $bookingRepository): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $trainerId = $user->getId();
        $now = new \DateTime();

        // 1. Total courses coached (All-time past)
        $totalCourses = $courseRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.user = :trainerId')
            ->andWhere('c.startTime < :now')
            ->setParameter('trainerId', $trainerId)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        // 2. Courses coached per month (Last 12 months)
        $twelveMonthsAgo = (new \DateTime())->modify('-12 months');
        $pastCourses = $courseRepository->createQueryBuilder('c')
            ->where('c.user = :trainerId')
            ->andWhere('c.startTime >= :twelveMonthsAgo')
            ->andWhere('c.startTime < :now')
            ->setParameter('trainerId', $trainerId)
            ->setParameter('twelveMonthsAgo', $twelveMonthsAgo)
            ->setParameter('now', $now)
            ->orderBy('c.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $monthlyStats = [];
        // Initialize last 12 months with 0
        $current = clone $twelveMonthsAgo;
        for ($i = 0; $i <= 12; $i++) {
            $monthlyStats[$current->format('Y-m')] = 0;
            $current->modify('+1 month');
        }

        foreach ($pastCourses as $course) {
            $month = $course->getStartTime()->format('Y-m');
            if (isset($monthlyStats[$month])) {
                $monthlyStats[$month]++;
            }
        }
        
        $formattedMonthlyStats = [];
        foreach ($monthlyStats as $month => $count) {
            $formattedMonthlyStats[] = ['month' => $month, 'count' => $count];
        }

        // 3. Average class capacity/fill rate
        $fillRates = [];
        foreach ($pastCourses as $course) {
            if ($course->getCapacity() > 0) {
                // Only consider courses that actually have a capacity set
                $fillRates[] = count($course->getBookings()) / $course->getCapacity();
            }
        }
        $averageFillRate = count($fillRates) > 0 ? array_sum($fillRates) / count($fillRates) : 0;

        // 4. Total unique members coached
        $uniqueMembers = $bookingRepository->createQueryBuilder('b')
            ->select('COUNT(DISTINCT u.id)')
            ->join('b.course', 'c')
            ->join('b.user', 'u')
            ->where('c.user = :trainerId')
            ->setParameter('trainerId', $trainerId)
            ->getQuery()
            ->getSingleScalarResult();

        // 5. Most popular time slot (hour of day)
        $timeSlots = [];
        foreach ($pastCourses as $course) {
            $hour = $course->getStartTime()->format('H');
            if (!isset($timeSlots[$hour])) {
                $timeSlots[$hour] = 0;
            }
            $timeSlots[$hour]++;
        }
        arsort($timeSlots);
        $popularTimeSlots = [];
        foreach (array_slice($timeSlots, 0, 5, true) as $hour => $count) {
            $popularTimeSlots[] = ['hour' => (int)$hour . ':00', 'count' => $count];
        }

        // 6. Most popular course types (by title)
        $courseTypes = [];
        foreach ($pastCourses as $course) {
            $title = $course->getTitle();
            if (!isset($courseTypes[$title])) {
                $courseTypes[$title] = 0;
            }
            $courseTypes[$title]++;
        }
        arsort($courseTypes);
        $popularCourseTypes = [];
        foreach (array_slice($courseTypes, 0, 5, true) as $title => $count) {
            $popularCourseTypes[] = ['title' => $title, 'count' => $count];
        }

        return new JsonResponse([
            'totalCourses' => (int)$totalCourses,
            'monthlyStats' => $formattedMonthlyStats,
            'averageFillRate' => round($averageFillRate * 100, 1),
            'uniqueMembers' => (int)$uniqueMembers,
            'popularTimeSlots' => $popularTimeSlots,
            'popularCourseTypes' => $popularCourseTypes
        ]);
    }
}
