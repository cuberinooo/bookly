<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TrainerStatisticsFilterTest extends WebTestCase
{
    private function createCompany($entityManager): Company
    {
        $company = new Company();
        $company->setName('Test Company '.uniqid());
        $entityManager->persist($company);
        $entityManager->flush();

        return $company;
    }

    private function createTrainer($entityManager, Company $company, $container): User
    {
        $trainer = new User();
        $trainer->setEmail('trainer'.uniqid().'@example.com');
        $trainer->setName('Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);

        $hasher = $container->get('security.user_password_hasher');
        $hashedPassword = $hasher->hashPassword($trainer, 'password');
        $trainer->setPassword($hashedPassword);

        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);
        $entityManager->flush();

        return $trainer;
    }

    private function createMember($entityManager, Company $company, $container): User
    {
        $member = new User();
        $member->setEmail('member'.uniqid().'@example.com');
        $member->setName('Member');
        $member->setRoles(['ROLE_USER']);

        $hasher = $container->get('security.user_password_hasher');
        $hashedPassword = $hasher->hashPassword($member, 'password');
        $member->setPassword($hashedPassword);

        $member->setIsVerified(true);
        $member->setCompany($company);
        $entityManager->persist($member);
        $entityManager->flush();

        return $member;
    }

    public function test_statistics_filter_by_start_date(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = $this->createCompany($entityManager);
        $trainer = $this->createTrainer($entityManager, $company, $container);
        $member = $this->createMember($entityManager, $company, $container);

        // 1. Create a course 10 days ago (past)
        $course1 = new Course();
        $course1->setTitle('Past Course 1');
        $course1->setUser($trainer);
        $course1->setCompany($company);
        $course1->setStartTime(new \DateTime('-10 days'));
        $course1->setEndTime(new \DateTime('-10 days + 1 hour'));
        $course1->setCapacity(10);
        $course1->setStatus(CourseStatus::ACTIVE);
        $entityManager->persist($course1);

        $booking1 = new Booking();
        $booking1->setUser($member);
        $booking1->setCourse($course1);
        $booking1->setCompany($company);
        $entityManager->persist($booking1);

        // 2. Create a course 2 days ago (recent past)
        $course2 = new Course();
        $course2->setTitle('Past Course 2');
        $course2->setUser($trainer);
        $course2->setCompany($company);
        $course2->setStartTime(new \DateTime('-2 days'));
        $course2->setEndTime(new \DateTime('-2 days + 1 hour'));
        $course2->setCapacity(10);
        $course2->setStatus(CourseStatus::ACTIVE);
        $entityManager->persist($course2);

        $booking2 = new Booking();
        $booking2->setUser($member);
        $booking2->setCourse($course2);
        $booking2->setCompany($company);
        $entityManager->persist($booking2);

        $entityManager->flush();

        $authHeader = ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode($trainer->getEmail().':password')];

        // 3. Request without filter -> should return both courses
        $client->request('GET', '/api/trainer/statistics', [], [], $authHeader);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $stats = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(2, $stats['totalCourses']);
        $this->assertEquals(1, $stats['uniqueMembers']);

        // 4. Filter starting 5 days ago -> should only count the 2 days ago course
        $fiveDaysAgoStr = (new \DateTime('-5 days'))->format('Y-m-d');
        $client->request('GET', '/api/trainer/statistics?startDate='.$fiveDaysAgoStr, [], [], $authHeader);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $statsFiltered = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(1, $statsFiltered['totalCourses']);
        $this->assertEquals(1, $statsFiltered['uniqueMembers']);

        // 5. Filter starting 1 day ago -> should return 0 courses
        $oneDayAgoStr = (new \DateTime('-1 day'))->format('Y-m-d');
        $client->request('GET', '/api/trainer/statistics?startDate='.$oneDayAgoStr, [], [], $authHeader);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $statsFilteredEmpty = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(0, $statsFilteredEmpty['totalCourses']);
        $this->assertEquals(0, $statsFilteredEmpty['uniqueMembers']);
    }
}
