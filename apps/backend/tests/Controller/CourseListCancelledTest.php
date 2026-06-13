<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseListCancelledTest extends WebTestCase
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

    public function test_cancelled_courses_are_included_in_list(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = $this->createCompany($entityManager);
        $trainer = $this->createTrainer($entityManager, $company, $container);

        // 1. Create an active course
        $activeCourse = new Course();
        $activeCourse->setTitle('Active Course');
        $activeCourse->setUser($trainer);
        $activeCourse->setCompany($company);
        $activeCourse->setStartTime(new \DateTime('+1 hour'));
        $activeCourse->setEndTime(new \DateTime('+2 hours'));
        $activeCourse->setCapacity(10);
        $entityManager->persist($activeCourse);

        // 2. Create a cancelled course
        $cancelledCourse = new Course();
        $cancelledCourse->setTitle('Cancelled Course');
        $cancelledCourse->setUser($trainer);
        $cancelledCourse->setCompany($company);
        $cancelledCourse->setStartTime(new \DateTime('+3 hours'));
        $cancelledCourse->setEndTime(new \DateTime('+4 hours'));
        $cancelledCourse->setCapacity(10);
        $cancelledCourse->setStatus(CourseStatus::CANCELLED);
        $cancelledCourse->setCancelledBy($trainer);
        $entityManager->persist($cancelledCourse);

        $entityManager->flush();

        $authHeader = ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode($trainer->getEmail().':password')];

        // 3. Fetch courses
        $client->request('GET', '/api/courses', [], [], $authHeader);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $content = json_decode($client->getResponse()->getContent(), true);
        $courses = $content['data'];

        $courseTitles = array_map(fn ($c) => $c['title'], $courses);

        $this->assertContains('Active Course', $courseTitles);
        $this->assertContains('Cancelled Course', $courseTitles, 'Cancelled course should be included in the list');

        // Find the cancelled course in response
        $cancelledCourseData = null;
        foreach ($courses as $c) {
            if ('Cancelled Course' === $c['title']) {
                $cancelledCourseData = $c;
                break;
            }
        }

        $this->assertNotNull($cancelledCourseData);
        $this->assertEquals('cancelled', $cancelledCourseData['status']);
        $this->assertEquals($trainer->getName(), $cancelledCourseData['cancelledBy']['name']);
    }
}
