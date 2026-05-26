<?php

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseListPostponedTest extends WebTestCase
{
    private function createCompany($entityManager): Company
    {
        $company = new Company();
        $company->setName('Test Company ' . uniqid());
        $entityManager->persist($company);
        $entityManager->flush();
        return $company;
    }

    private function createTrainer($entityManager, Company $company, $container): User
    {
        $trainer = new User();
        $trainer->setEmail('trainer' . uniqid() . '@example.com');
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

    public function testPostponedCoursesAreIncludedInList(): void
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
        $activeCourse->setStatus(CourseStatus::ACTIVE);
        $entityManager->persist($activeCourse);

        // 2. Create a postponed course
        $postponedCourse = new Course();
        $postponedCourse->setTitle('Postponed Course');
        $postponedCourse->setUser($trainer);
        $postponedCourse->setCompany($company);
        $postponedCourse->setStartTime(new \DateTime('+3 hours'));
        $postponedCourse->setEndTime(new \DateTime('+4 hours'));
        $postponedCourse->setCapacity(10);
        $postponedCourse->setStatus(CourseStatus::POSTPONED);
        $postponedCourse->setPostponedBy($trainer);
        $entityManager->persist($postponedCourse);
        
        $entityManager->flush();

        $authHeader = ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($trainer->getEmail() . ':password')];

        // 3. Request course list (like the dashboard/calendar does)
        $client->request('GET', '/api/courses?all=true', [], [], $authHeader);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $courseTitles = array_map(fn($c) => $c['title'], $data['data']);

        $this->assertContains('Active Course', $courseTitles);
        $this->assertContains('Postponed Course', $courseTitles, 'Postponed course should be included in the list');
    }
}
