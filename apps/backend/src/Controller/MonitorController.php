<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Entity\Booking;
use App\Entity\SensitiveDataAccessLog;
use App\Entity\Meetup;
use App\Entity\MeetupComment;
use App\Entity\MeetupRsvp;
use App\Entity\MeetupUserReadState;
use App\Entity\TrainingCycle;
use App\Entity\TrainingCategory;
use App\Repository\CompanyRepository;
use App\Repository\UserRepository;
use App\Repository\CourseRepository;
use App\Repository\CourseSeriesRepository;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/monitor')]
#[IsGranted('ROLE_MONITOR')]
class MonitorController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/companies', name: 'monitor_companies_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('company_filter')) {
            $filters->disable('company_filter');
        }

        $companies = $this->entityManager->createQueryBuilder()
            ->select('c', 'a', 's', 'st')
            ->from(Company::class, 'c')
            ->leftJoin('c.adminSettings', 'a')
            ->leftJoin('c.smtpSettings', 's')
            ->leftJoin('c.stripeConfig', 'st')
            ->getQuery()
            ->getResult();

        $now = new \DateTimeImmutable();
        $nowStr = $now->format('Y-m-d H:i:s');

        // 1. Fetch course counts per company
        $courseCountsRaw = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(c.company) as companyId, COUNT(c.id) as cnt')
            ->from(Course::class, 'c')
            ->groupBy('c.company')
            ->getQuery()
            ->getResult();
        $courseCounts = [];
        foreach ($courseCountsRaw as $row) {
            $courseCounts[(int)$row['companyId']] = (int)$row['cnt'];
        }

        // 2. Fetch course series counts per company
        $seriesCountsRaw = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(cs.company) as companyId, COUNT(cs.id) as cnt')
            ->from(CourseSeries::class, 'cs')
            ->groupBy('cs.company')
            ->getQuery()
            ->getResult();
        $seriesCounts = [];
        foreach ($seriesCountsRaw as $row) {
            $seriesCounts[(int)$row['companyId']] = (int)$row['cnt'];
        }

        // 3. Fetch user counts per company (total, active, inactive)
        $userCountsRaw = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(u.company) as companyId, COUNT(u.id) as total, SUM(CASE WHEN u.isActive = true THEN 1 ELSE 0 END) as active, SUM(CASE WHEN u.isActive = false THEN 1 ELSE 0 END) as inactive')
            ->from(User::class, 'u')
            ->groupBy('u.company')
            ->getQuery()
            ->getResult();
        $userCounts = [];
        foreach ($userCountsRaw as $row) {
            $userCounts[(int)$row['companyId']] = [
                'total' => (int)$row['total'],
                'active' => (int)$row['active'],
                'inactive' => (int)$row['inactive'],
            ];
        }

        // 4. Fetch booking counts per company (total, upcoming)
        $bookingCountsRaw = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(b.company) as companyId, COUNT(b.id) as total, SUM(CASE WHEN c.startTime > :now THEN 1 ELSE 0 END) as upcoming')
            ->from(Booking::class, 'b')
            ->leftJoin('b.course', 'c')
            ->groupBy('b.company')
            ->setParameter('now', $nowStr)
            ->getQuery()
            ->getResult();
        $bookingCounts = [];
        foreach ($bookingCountsRaw as $row) {
            $bookingCounts[(int)$row['companyId']] = [
                'total' => (int)$row['total'],
                'upcoming' => (int)$row['upcoming'],
            ];
        }

        $data = [];
        foreach ($companies as $company) {
            $companyId = $company->getId();
            $adminSettings = $company->getAdminSettings();
            $smtpSettings = $company->getSmtpSettings();
            $stripeConfig = $company->getStripeConfig();

            $totalCourses = $courseCounts[$companyId] ?? 0;
            $totalCourseSeries = $seriesCounts[$companyId] ?? 0;
            $uCounts = $userCounts[$companyId] ?? ['total' => 0, 'active' => 0, 'inactive' => 0];
            $bCounts = $bookingCounts[$companyId] ?? ['total' => 0, 'upcoming' => 0];

            $smtpEmail = null;
            if ($smtpSettings && $smtpSettings->isUseCustomSmtp()) {
                $smtpEmail = $smtpSettings->getUsername();
            }

            $legalNotice = null;
            if ($adminSettings) {
                $legalNotice = [
                    'representative' => $adminSettings->getLegalNoticeRepresentative(),
                    'street' => $adminSettings->getLegalNoticeStreet(),
                    'houseNumber' => $adminSettings->getLegalNoticeHouseNumber(),
                    'zipCode' => $adminSettings->getLegalNoticeZipCode(),
                    'city' => $adminSettings->getLegalNoticeCity(),
                    'email' => $adminSettings->getLegalNoticeEmail(),
                    'phone' => $adminSettings->getLegalNoticePhone(),
                    'taxId' => $adminSettings->getLegalNoticeTaxId(),
                    'vatId' => $adminSettings->getLegalNoticeVatId(),
                ];
            }

            $data[] = [
                'id' => $companyId,
                'name' => $company->getName(),
                'createdAt' => $company->getCreatedAt() ? $company->getCreatedAt()->format(\DateTimeInterface::ATOM) : null,
                'smtpEmail' => $smtpEmail,
                'customSmtpEnabled' => $smtpSettings ? $smtpSettings->isUseCustomSmtp() : false,
                'legalNotice' => $legalNotice,
                'insights' => [
                    'totalCourses' => $totalCourses,
                    'totalCourseSeries' => $totalCourseSeries,
                    'totalUsers' => $uCounts['total'],
                    'activeUsers' => $uCounts['active'],
                    'inactiveUsers' => $uCounts['inactive'],
                    'isPaymentActive' => $stripeConfig ? $stripeConfig->isPaymentEnabled() : false,
                    'stripeAccountId' => $stripeConfig ? $stripeConfig->getStripeAccountId() : null,
                    'totalBookings' => $bCounts['total'],
                    'upcomingBookings' => $bCounts['upcoming'],
                ]
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/companies/{id}/users', name: 'monitor_company_users', methods: ['GET'])]
    public function companyUsers(Company $company): JsonResponse
    {
        $viewer = $this->getUser();
        if (!$viewer instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('company_filter')) {
            $filters->disable('company_filter');
        }

        $users = $this->userRepository->findBy(['company' => $company]);

        // Log sensitive data access for each user
        foreach ($users as $user) {
            $log = new SensitiveDataAccessLog();
            $log->setViewer($viewer);
            $log->setTargetUser($user);
            $log->setReason('Monitor accessed company users list');
            $this->entityManager->persist($log);
        }
        $this->entityManager->flush();

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/companies/{id}', name: 'monitor_company_delete', methods: ['DELETE'])]
    public function deleteCompany(Company $company): JsonResponse
    {
        $filters = $this->entityManager->getFilters();
        $wasFilterEnabled = $filters->isEnabled('company_filter');
        if ($wasFilterEnabled) {
            $filters->disable('company_filter');
        }

        try {
            $stripeConfig = $company->getStripeConfig();
            if ($stripeConfig && $stripeConfig->isPaymentEnabled()) {
                return new JsonResponse([
                    'error' => 'Cannot delete company with active payment.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $users = $this->userRepository->findBy(['company' => $company]);
            if (count($users) > 1) {
                return new JsonResponse([
                    'error' => 'Cannot delete company with more than 1 user account.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
            
            $recentBookingsCount = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(b.id)')
                ->from(Booking::class, 'b')
                ->where('b.company = :company')
                ->andWhere('b.createdAt >= :thirtyDaysAgo')
                ->setParameter('company', $company)
                ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
                ->getQuery()
                ->getSingleScalarResult();

            if ($recentBookingsCount > 0) {
                return new JsonResponse([
                    'error' => 'Cannot delete company with activity in the last 30 days.'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (count($users) > 0) {
                $this->entityManager->createQueryBuilder()
                    ->delete(SensitiveDataAccessLog::class, 'log')
                    ->where('log.viewer IN (:users)')
                    ->orWhere('log.targetUser IN (:users)')
                    ->setParameter('users', $users)
                    ->getQuery()
                    ->execute();
            }

            $this->entityManager->createQueryBuilder()
                ->delete(Booking::class, 'b')
                ->where('b.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(MeetupComment::class, 'mc')
                ->where('mc.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(MeetupRsvp::class, 'mr')
                ->where('mr.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(MeetupUserReadState::class, 'ms')
                ->where('ms.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(Meetup::class, 'm')
                ->where('m.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(TrainingCycle::class, 'tc')
                ->where('tc.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(TrainingCategory::class, 'tcat')
                ->where('tcat.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(CourseSeries::class, 'cs')
                ->where('cs.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(Course::class, 'c')
                ->where('c.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->createQueryBuilder()
                ->delete(User::class, 'u')
                ->where('u.company = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->execute();

            $this->entityManager->remove($company);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);

        } finally {
            if ($wasFilterEnabled) {
                $filters->enable('company_filter');
            }
        }
    }
}
