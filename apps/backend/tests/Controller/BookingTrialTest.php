<?php

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use App\Entity\GlobalSettings;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class BookingTrialTest extends WebTestCase
{
    public function testTrialMemberCanBookAndUnbook(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        $suffix = uniqid();

        // Create Company and Settings
        $company = new Company();
        $company->setName('Trial Test Company ' . $suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $settings->setTrialBookingLimit(5);
        $entityManager->persist($settings);
        $company->setGlobalSettings($settings);

        // Create Trainer
        $trainer = new User();
        $trainer->setEmail('trainer_trial_' . $suffix . '@example.com');
        $trainer->setName('Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password'); // Note: in real app this would be hashed, but http_basic works with plaintext in tests if provider is entity
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        // Create Trial User
        $trialUser = new User();
        $trialUser->setEmail('trial_' . $suffix . '@example.com');
        $trialUser->setName('Trial Athlete');
        $trialUser->setRoles(['ROLE_TRIAL']);
        // Hash the password so http_basic can verify it
        $hasher = static::getContainer()->get('security.password_hasher');
        $trialUser->setPassword($hasher->hashPassword($trialUser, 'password'));
        $trialUser->setIsVerified(true);
        $trialUser->setCompany($company);
        $entityManager->persist($trialUser);

        // Create Course
        $course = new Course();
        $course->setTitle('Trial Eligible Course');
        $course->setUser($trainer);
        $course->setCompany($company);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        $entityManager->flush();

        $authHeaders = [
            'PHP_AUTH_USER' => $trialUser->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ];

        // 1. Attempt to book
        $client->request('POST', '/api/courses/' . $course->getId() . '/book', [], [], $authHeaders);
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), 'Trial user should be able to book');

        // 2. Attempt to unbook
        $client->request('DELETE', '/api/courses/' . $course->getId() . '/book', [], [], $authHeaders);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Trial user should be able to unbook');
    }

    public function testTrialBookingLimitEnforcement(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        $suffix = uniqid();

        // Create Company and Settings (Limit = 1)
        $company = new Company();
        $company->setName('Limit Test Company ' . $suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $settings->setTrialBookingLimit(1);
        $entityManager->persist($settings);
        $company->setGlobalSettings($settings);

        // Create Trial User
        $trialUser = new User();
        $trialUser->setEmail('trial_limit_' . $suffix . '@example.com');
        $trialUser->setName('Trial Athlete');
        $trialUser->setRoles(['ROLE_TRIAL']);
        $hasher = static::getContainer()->get('security.password_hasher');
        $trialUser->setPassword($hasher->hashPassword($trialUser, 'password'));
        $trialUser->setIsVerified(true);
        $trialUser->setCompany($company);
        $entityManager->persist($trialUser);

        // Create Trainer
        $trainer = new User();
        $trainer->setEmail('trainer_limit_' . $suffix . '@example.com');
        $trainer->setName('Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        // Create 2 Courses
        $course1 = new Course();
        $course1->setTitle('Course 1');
        $course1->setUser($trainer);
        $course1->setCompany($company);
        $course1->setStartTime(new \DateTime('+1 day'));
        $course1->setEndTime(new \DateTime('+1 day 1 hour'));
        $course1->setCapacity(10);
        $entityManager->persist($course1);

        $course2 = new Course();
        $course2->setTitle('Course 2');
        $course2->setUser($trainer);
        $course2->setCompany($company);
        $course2->setStartTime(new \DateTime('+2 days'));
        $course2->setEndTime(new \DateTime('+2 days 1 hour'));
        $course2->setCapacity(10);
        $entityManager->persist($course2);

        $entityManager->flush();

        $authHeaders = [
            'PHP_AUTH_USER' => $trialUser->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ];

        // 1. Book Course 1 (Should succeed)
        $client->request('POST', '/api/courses/' . $course1->getId() . '/book', [], [], $authHeaders);
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        // 2. Book Course 2 (Should fail due to limit)
        $client->request('POST', '/api/courses/' . $course2->getId() . '/book', [], [], $authHeaders);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Trial limit reached', $response['error']);
    }
}
