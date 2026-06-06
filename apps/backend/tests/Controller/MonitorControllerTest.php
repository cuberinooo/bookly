<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\SensitiveDataAccessLog;
use App\Entity\Course;
use App\Entity\Booking;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class MonitorControllerTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->userRepository = static::getContainer()->get(UserRepository::class);
    }

    private function getToken(User $user): string
    {
        return static::getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(string $email, array $roles): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $entityManager = static::getContainer()->get('doctrine')->getManager();

            $company = new Company();
            $company->setName('Test Company ' . uniqid());

            $entityManager->persist($company);

            $user = new User();
            $user->setEmail($email);
            $user->setRoles($roles);
            $user->setPassword('password');
            $user->setName('Test User');
            $user->setIsVerified(true);
            $user->setIsActive(true);
            $user->setCompany($company);

            $entityManager->persist($user);
            $entityManager->flush();
        }

        return $user;
    }

    public function test_access_requires_role_monitor(): void
    {
        // 1. Unauthorized
        $this->client->request('GET', '/api/monitor/companies');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        // 2. Forbidden for ROLE_ADMIN
        $admin = $this->createUser('admin_monitor_test@example.com', ['ROLE_ADMIN']);
        $adminToken = $this->getToken($admin);
        $this->client->request('GET', '/api/monitor/companies', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // 3. Successful for ROLE_MONITOR
        $monitor = $this->createUser('monitor_test@example.com', ['ROLE_MONITOR']);
        $monitorToken = $this->getToken($monitor);
        $this->client->request('GET', '/api/monitor/companies', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseContent);
        $this->assertNotEmpty($responseContent);

        // Validate structure of first company
        $companyData = $responseContent[0];
        $this->assertArrayHasKey('id', $companyData);
        $this->assertArrayHasKey('name', $companyData);
        $this->assertArrayHasKey('createdAt', $companyData);
        $this->assertArrayHasKey('smtpEmail', $companyData);
        $this->assertArrayHasKey('customSmtpEnabled', $companyData);
        $this->assertArrayHasKey('legalNotice', $companyData);
        $this->assertArrayHasKey('insights', $companyData);

        $insights = $companyData['insights'];
        $this->assertArrayHasKey('totalCourses', $insights);
        $this->assertArrayHasKey('totalCourseSeries', $insights);
        $this->assertArrayHasKey('totalUsers', $insights);
        $this->assertArrayHasKey('activeUsers', $insights);
        $this->assertArrayHasKey('inactiveUsers', $insights);
        $this->assertArrayHasKey('isPaymentActive', $insights);
        $this->assertArrayHasKey('stripeAccountId', $insights);
        $this->assertArrayHasKey('totalBookings', $insights);
        $this->assertArrayHasKey('upcomingBookings', $insights);
    }

    public function test_access_logs_sensitive_data(): void
    {
        $monitor = $this->createUser('monitor_test@example.com', ['ROLE_MONITOR']);
        $monitorToken = $this->getToken($monitor);

        $companyId = $monitor->getCompany()->getId();

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $logRepo = $entityManager->getRepository(SensitiveDataAccessLog::class);
        $initialLogCount = count($logRepo->findAll());

        $this->client->request('GET', '/api/monitor/companies/'.$companyId.'/users', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
        ]);
        $this->assertResponseIsSuccessful();

        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseContent);

        // Accessing the users list must log access
        $finalLogCount = count($logRepo->findAll());
        $this->assertGreaterThan($initialLogCount, $finalLogCount);

        // Check format
        foreach ($responseContent as $u) {
            $this->assertArrayHasKey('id', $u);
            $this->assertArrayHasKey('name', $u);
            $this->assertArrayHasKey('email', $u);
            $this->assertArrayNotHasKey('phoneNumber', $u);
        }
    }

    public function test_delete_company_constraints(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $monitor = $this->createUser('monitor_test@example.com', ['ROLE_MONITOR']);
        $monitorToken = $this->getToken($monitor);

        // 1. Create a dummy company
        $company = new Company();
        $company->setName('Deletable Test Company ' . uniqid());
        
        $stripeConfig = $company->getStripeConfig();
        $stripeConfig->setPaymentEnabled(true); // Active payment initially

        $entityManager->persist($company);
        $entityManager->flush();

        $companyId = $company->getId();

        // Try to delete with active payment -> should fail (400)
        $this->client->request('DELETE', '/api/monitor/companies/'.$companyId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertStringContainsString('Cannot delete company with active payment', $this->client->getResponse()->getContent());

        // Deactivate payment
        $stripeConfig->setPaymentEnabled(false);
        $entityManager->flush();

        // Create a user in that company
        $user1 = new User();
        $user1->setEmail('cuser1_'.uniqid().'@example.com');
        $user1->setPassword('pass');
        $user1->setName('Company User 1');
        $user1->setCompany($company);
        $entityManager->persist($user1);

        $user2 = new User();
        $user2->setEmail('cuser2_'.uniqid().'@example.com');
        $user2->setPassword('pass');
        $user2->setName('Company User 2');
        $user2->setCompany($company);
        $entityManager->persist($user2);
        
        $entityManager->flush();

        // Try to delete with 2 users -> should fail (400)
        $this->client->request('DELETE', '/api/monitor/companies/'.$companyId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertStringContainsString('Cannot delete company with more than 1 user account', $this->client->getResponse()->getContent());

        // Delete user2
        $user2 = $entityManager->getRepository(User::class)->find($user2->getId());
        $entityManager->remove($user2);
        $entityManager->flush();

        // Fetch fresh company and user1 because client request boots a new kernel/EM
        $company = $entityManager->getRepository(Company::class)->find($companyId);
        $user1 = $entityManager->getRepository(User::class)->find($user1->getId());

        // Create a course and booking in the last 30 days
        $course = new Course();
        $course->setTitle('Test Course');
        $course->setCompany($company);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+2 hours'));
        $course->setCapacity(10);
        $course->setUser($user1); // Trainer
        $entityManager->persist($course);

        $booking = new Booking();
        $booking->setCompany($company);
        $booking->setUser($user1);
        $booking->setCourse($course);
        $booking->setCreatedAt(new \DateTimeImmutable()); // Now (within last 30 days)
        $entityManager->persist($booking);

        $entityManager->flush();

        // Try to delete with recent booking activity -> should fail (400)
        $this->client->request('DELETE', '/api/monitor/companies/'.$companyId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertStringContainsString('Cannot delete company with activity in the last 30 days', $this->client->getResponse()->getContent());

        // Remove the booking
        $entityManager->remove($booking);
        $entityManager->remove($course);
        $entityManager->flush();

        // Delete should now succeed
        $this->client->request('DELETE', '/api/monitor/companies/'.$companyId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
        ]);
        $this->assertResponseIsSuccessful();

        // Check if company is gone
        $entityManager->clear();
        $deletedCompany = $entityManager->getRepository(Company::class)->find($companyId);
        $this->assertNull($deletedCompany);
    }
}
