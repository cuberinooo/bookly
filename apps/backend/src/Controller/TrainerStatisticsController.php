<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Repository\CourseRepository;
use App\Service\ApiCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/trainer/statistics')]
#[IsGranted('ROLE_TRAINER')]
class TrainerStatisticsController extends AbstractController
{
    public function __construct(
        private readonly ApiCacheService $apiCache
    ) {
    }

    #[Route('', name: 'trainer_statistics', methods: ['GET'])]
    public function getStatistics(CourseRepository $courseRepository, BookingRepository $bookingRepository): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $trainerId = $user->getId();
        $companyId = $user->getCompany()->getId();

        $context = [
            'trainerId' => $trainerId,
        ];

        $data = $this->apiCache->get('course', $companyId, $context, function () use ($trainerId, $companyId, $courseRepository, $bookingRepository) {
            $now = new \DateTime();

            // 1. Total courses coached (All-time past) - TRAINER SPECIFIC
            $totalCourses = $courseRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.user = :trainerId')
                ->andWhere('c.startTime < :now')
                ->andWhere('c.status = :status')
                ->setParameter('trainerId', $trainerId)
                ->setParameter('now', $now)
                ->setParameter('status', \App\Enum\CourseStatus::ACTIVE)
                ->getQuery()
                ->getSingleScalarResult();

            // 2. Courses coached per month (Last 12 months) - TRAINER SPECIFIC
            $twelveMonthsAgo = (new \DateTime())->modify('-12 months');
            $pastCourses = $courseRepository->createQueryBuilder('c')
                ->where('c.user = :trainerId')
                ->andWhere('c.startTime >= :twelveMonthsAgo')
                ->andWhere('c.startTime < :now')
                ->andWhere('c.status = :status')
                ->setParameter('trainerId', $trainerId)
                ->setParameter('twelveMonthsAgo', $twelveMonthsAgo)
                ->setParameter('now', $now)
                ->setParameter('status', \App\Enum\CourseStatus::ACTIVE)
                ->orderBy('c.startTime', 'ASC')
                ->getQuery()
                ->getResult();

            // General Company Courses (Last 12 months) - FOR GLOBAL INSIGHTS
            $allPastCourses = $courseRepository->createQueryBuilder('c')
                ->where('c.company = :companyId')
                ->andWhere('c.startTime >= :twelveMonthsAgo')
                ->andWhere('c.startTime < :now')
                ->andWhere('c.status = :status')
                ->setParameter('companyId', $companyId)
                ->setParameter('twelveMonthsAgo', $twelveMonthsAgo)
                ->setParameter('now', $now)
                ->setParameter('status', \App\Enum\CourseStatus::ACTIVE)
                ->getQuery()
                ->getResult();

            $monthlyStats = [];
            // Initialize last 12 months with 0
            $current = clone $twelveMonthsAgo;
            for ($i = 0; $i <= 12; ++$i) {
                $monthlyStats[$current->format('Y-m')] = 0;
                $current->modify('+1 month');
            }

            foreach ($pastCourses as $course) {
                $month = $course->getStartTime()->format('Y-m');
                if (isset($monthlyStats[$month])) {
                    ++$monthlyStats[$month];
                }
            }

            $formattedMonthlyStats = [];
            foreach ($monthlyStats as $month => $count) {
                $formattedMonthlyStats[] = ['month' => $month, 'count' => $count];
            }

            // 3. Average class capacity/fill rate (GENERAL)
            $fillRates = [];
            foreach ($allPastCourses as $course) {
                if ($course->getCapacity() > 0) {
                    // Only consider courses that actually have a capacity set
                    $fillRates[] = count($course->getBookings()) / $course->getCapacity();
                }
            }
            $averageFillRate = count($fillRates) > 0 ? array_sum($fillRates) / count($fillRates) : 0;

            // 4. Total unique members coached (TRAINER SPECIFIC)
            $uniqueMembers = $bookingRepository->createQueryBuilder('b')
                ->select('COUNT(DISTINCT u.id)')
                ->join('b.course', 'c')
                ->join('b.user', 'u')
                ->where('c.user = :trainerId')
                ->andWhere('c.status = :status')
                ->setParameter('trainerId', $trainerId)
                ->setParameter('status', \App\Enum\CourseStatus::ACTIVE)
                ->getQuery()
                ->getSingleScalarResult();

            // 5. Popular time slot (hour of day) (GENERAL)
            $timeSlots = [];
            foreach ($allPastCourses as $course) {
                $hour = $course->getStartTime()->format('H');
                if (!isset($timeSlots[$hour])) {
                    $timeSlots[$hour] = ['sessions' => 0, 'bookings' => 0];
                }
                ++$timeSlots[$hour]['sessions'];
                $timeSlots[$hour]['bookings'] += count($course->getBookings());
            }
            
            // Sort by bookings first, then sessions
            uasort($timeSlots, function($a, $b) {
                return $b['bookings'] <=> $a['bookings'] ?: $b['sessions'] <=> $a['sessions'];
            });
            
            $popularTimeSlots = [];
            foreach (array_slice($timeSlots, 0, 5, true) as $hour => $stats) {
                $popularTimeSlots[] = [
                    'hour' => (int) $hour.':00', 
                    'count' => $stats['sessions'],
                    'attempts' => $stats['bookings']
                ];
            }

            // 6. Most popular course types (by title) (GENERAL)
            $courseTypes = [];
            foreach ($allPastCourses as $course) {
                $title = $course->getTitle();
                if (!isset($courseTypes[$title])) {
                    $courseTypes[$title] = ['sessions' => 0, 'bookings' => 0];
                }
                ++$courseTypes[$title]['sessions'];
                $courseTypes[$title]['bookings'] += count($course->getBookings());
            }
            
            uasort($courseTypes, function($a, $b) {
                return $b['bookings'] <=> $a['bookings'] ?: $b['sessions'] <=> $a['sessions'];
            });
            
            $popularCourseTypes = [];
            foreach (array_slice($courseTypes, 0, 5, true) as $title => $stats) {
                $popularCourseTypes[] = [
                    'title' => $title, 
                    'count' => $stats['sessions'],
                    'attempts' => $stats['bookings']
                ];
            }

            // 7. Most popular days of the week (GENERAL)
            $daysOfWeek = [];
            foreach ($allPastCourses as $course) {
                $day = $course->getStartTime()->format('l'); // 'Monday', 'Tuesday', etc.
                if (!isset($daysOfWeek[$day])) {
                    $daysOfWeek[$day] = ['sessions' => 0, 'bookings' => 0];
                }
                ++$daysOfWeek[$day]['sessions'];
                $daysOfWeek[$day]['bookings'] += count($course->getBookings());
            }
            
            // For days of week, we keep the natural order but we want to return both stats
            $popularDaysOfWeek = [];
            $orderedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($orderedDays as $day) {
                if (isset($daysOfWeek[$day])) {
                    $popularDaysOfWeek[] = [
                        'day' => $day, 
                        'count' => $daysOfWeek[$day]['sessions'],
                        'attempts' => $daysOfWeek[$day]['bookings']
                    ];
                }
            }

            return [
                'totalCourses' => (int) $totalCourses,
                'monthlyStats' => $formattedMonthlyStats,
                'averageFillRate' => round($averageFillRate * 100, 1),
                'uniqueMembers' => (int) $uniqueMembers,
                'popularTimeSlots' => $popularTimeSlots,
                'popularCourseTypes' => $popularCourseTypes,
                'popularDaysOfWeek' => $popularDaysOfWeek,
            ];
        }, 600);

        return new JsonResponse($data);
    }
}
