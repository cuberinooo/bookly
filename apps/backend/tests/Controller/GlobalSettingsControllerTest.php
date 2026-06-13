<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\GlobalSettings;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GlobalSettingsControllerTest extends WebTestCase
{
    private ?\Symfony\Bundle\FrameworkBundle\KernelBrowser $client = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function getToken(User $user): string
    {
        return static::getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    private function createTrainer(string $suffix): User
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $company = new Company();
        $company->setName('Global Settings Gym '.$suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $settings->setMaxTrialBookingsPerClass(2);
        $entityManager->persist($settings);
        $company->setGlobalSettings($settings);

        $trainer = new User();
        $trainer->setEmail('trainer_global_settings_'.$suffix.'@example.com');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setName('Trainer');
        $trainer->setIsVerified(true);
        $trainer->setIsActive(true);
        $trainer->setCompany($company);

        $entityManager->persist($trainer);
        $entityManager->flush();

        return $trainer;
    }

    public function test_get_and_patch_global_settings(): void
    {
        $suffix = uniqid();
        $trainer = $this->createTrainer($suffix);
        $token = $this->getToken($trainer);

        // 1. GET Settings
        $this->client->request(
            'GET',
            '/api/settings',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$token]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Verify default maxTrialBookingsPerClass is 2
        $this->assertArrayHasKey('maxTrialBookingsPerClass', $data);
        $this->assertEquals(2, $data['maxTrialBookingsPerClass']);

        // 2. PATCH Settings to update maxTrialBookingsPerClass to 5
        $this->client->request(
            'PATCH',
            '/api/settings',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['maxTrialBookingsPerClass' => 5])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // 3. GET Settings again and verify update
        $this->client->request(
            'GET',
            '/api/settings',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$token]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $dataUpdated = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals(5, $dataUpdated['maxTrialBookingsPerClass']);
    }

    public function test_cache_invalidated_on_settings_update(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $hasher = static::getContainer()->get('security.password_hasher');

        $suffix = uniqid();

        // Create Company and Settings (showParticipantNames = false)
        $company = new Company();
        $company->setName('Cache Invalid Gym '.$suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $settings->setShowParticipantNames(false);
        $entityManager->persist($settings);
        $company->setGlobalSettings($settings);

        // Create Trainer
        $trainer = new User();
        $trainer->setEmail('trainer_cache_'.$suffix.'@example.com');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword($hasher->hashPassword($trainer, 'password'));
        $trainer->setName('Trainer');
        $trainer->setIsVerified(true);
        $trainer->setIsActive(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        // Create Member 1 (to book)
        $member1 = new User();
        $member1->setEmail('member1_'.$suffix.'@example.com');
        $member1->setRoles(['ROLE_MEMBER']);
        $member1->setPassword($hasher->hashPassword($member1, 'password'));
        $member1->setName('Member One');
        $member1->setIsVerified(true);
        $member1->setIsActive(true);
        $member1->setCompany($company);
        $entityManager->persist($member1);

        // Create Member 2 (to view)
        $member2 = new User();
        $member2->setEmail('member2_'.$suffix.'@example.com');
        $member2->setRoles(['ROLE_MEMBER']);
        $member2->setPassword($hasher->hashPassword($member2, 'password'));
        $member2->setName('Member Two');
        $member2->setIsVerified(true);
        $member2->setIsActive(true);
        $member2->setCompany($company);
        $entityManager->persist($member2);

        // Create Course
        $course = new Course();
        $course->setTitle('Cache Course');
        $course->setUser($trainer);
        $course->setCompany($company);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        $entityManager->flush();

        // Member 1 books the course
        $bookingService = static::getContainer()->get(\App\Service\BookingService::class);
        $bookingService->book($course, $member1);
        $entityManager->clear();

        // Get tokens
        $tokenTrainer = $this->getToken($trainer);
        $tokenMember2 = $this->getToken($member2);

        // 1. GET courses as Member 2 (names should be hidden)
        $this->client->request(
            'GET',
            '/api/courses',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$tokenMember2]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $courses = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($courses);
        $coursesList = $courses['data'] ?? [];
        $this->assertNotEmpty($coursesList);

        // Find the booked user name in normalized course bookings
        $bookingUser = $coursesList[0]['bookings'][0]['user'];
        $this->assertEquals('Athlete #'.$member1->getId(), $bookingUser['name']);

        // 2. PATCH settings as Trainer to enable showParticipantNames
        $this->client->request(
            'PATCH',
            '/api/settings',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$tokenTrainer,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['showParticipantNames' => true])
        );
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // 3. GET courses as Member 2 again (should be updated to real names because cache was invalidated)
        $this->client->request(
            'GET',
            '/api/courses',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$tokenMember2]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $coursesUpdated = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($coursesUpdated);
        $coursesListUpdated = $coursesUpdated['data'] ?? [];
        $this->assertNotEmpty($coursesListUpdated);

        $bookingUserUpdated = $coursesListUpdated[0]['bookings'][0]['user'];
        $this->assertEquals('Member One', $bookingUserUpdated['name']);
    }
}
