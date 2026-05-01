<?php

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class MultiTenancyTest extends WebTestCase
{
    private function createCompany(\Doctrine\ORM\EntityManagerInterface $em, string $name): Company
    {
        $company = new Company();
        $company->setName($name);
        $em->persist($company);
        return $company;
    }

    private function createTrainer(\Doctrine\ORM\EntityManagerInterface $em, Company $company, string $email): User
    {
        $trainer = new User();
        $trainer->setEmail($email);
        $trainer->setName('Trainer ' . $email);
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $em->persist($trainer);
        return $trainer;
    }

    private function getToken($client, User $user): string
    {
        return $client->getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    public function testCourseIsolationBetweenCompanies(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        // 1. Setup Company A with a course
        $companyA = $this->createCompany($entityManager, 'Company A ' . uniqid());
        $trainerA = $this->createTrainer($entityManager, $companyA, 'trainerA' . uniqid() . '@example.com');
        
        $courseA = new Course();
        $courseA->setTitle('Company A Course');
        $courseA->setUser($trainerA);
        $courseA->setCompany($companyA);
        $courseA->setStartTime(new \DateTime('+1 day'));
        $courseA->setEndTime(new \DateTime('+1 day 1 hour'));
        $courseA->setCapacity(10);
        $entityManager->persist($courseA);

        // 2. Setup Company B with a course
        $companyB = $this->createCompany($entityManager, 'Company B ' . uniqid());
        $trainerB = $this->createTrainer($entityManager, $companyB, 'trainerB' . uniqid() . '@example.com');

        $courseB = new Course();
        $courseB->setTitle('Company B Course');
        $courseB->setUser($trainerB);
        $courseB->setCompany($companyB);
        $courseB->setStartTime(new \DateTime('+2 days'));
        $courseB->setEndTime(new \DateTime('+2 days 1 hour'));
        $courseB->setCapacity(10);
        $entityManager->persist($courseB);

        $entityManager->flush();
        $entityManager->clear();

        $courseBId = $courseB->getId();
        $courseAId = $courseA->getId();

        $tokenA = $this->getToken($client, $trainerA);
        $tokenB = $this->getToken($client, $trainerB);

        // 3. Login as Trainer A and check courses
        $client->request('GET', '/api/courses', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $courseTitles = array_map(fn($c) => $c['title'], $data['data']);
        
        $this->assertContains('Company A Course', $courseTitles);
        $this->assertNotContains('Company B Course', $courseTitles);

        // 4. Try to access Course B directly as Trainer A
        $client->request('GET', '/api/courses/' . $courseBId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA
        ]);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), 'Trainer A should not see Course B');

        // 5. Login as Trainer B and check courses
        $client->request('GET', '/api/courses', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true);
        $courseTitles = array_map(fn($c) => $c['title'], $data['data']);
        
        $this->assertContains('Company B Course', $courseTitles);
        $this->assertNotContains('Company A Course', $courseTitles);

        // 6. Try to access Course A directly as Trainer B
        $client->request('GET', '/api/courses/' . $courseAId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB
        ]);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), 'Trainer B should not see Course A');
    }
}
