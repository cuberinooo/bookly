<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends WebTestCase
{
    private function getToken($client, User $user): string
    {
        return $client->getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    public function test_profile_picture_upload(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Upload Test Company '.uniqid());
        $entityManager->persist($company);

        $user = new User();
        $user->setEmail('uploader'.uniqid().'@example.com');
        $user->setName('Uploader');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('password');
        $user->setIsVerified(true);
        $user->setCompany($company);
        $entityManager->persist($user);
        $entityManager->flush();

        $token = $this->getToken($client, $user);

        // Create a dummy file
        $filePath = tempnam(sys_get_temp_dir(), 'test_img');
        file_put_contents($filePath, 'dummy image content');
        $uploadedFile = new UploadedFile($filePath, 'test.png', 'image/png', null, true);

        $client->request('POST', '/api/user/profile-picture', [], ['file' => $uploadedFile], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('profilePicture', $data);

        // Verify entity updated
        $entityManager->clear();
        $updatedUser = $entityManager->getRepository(User::class)->find($user->getId());
        $this->assertNotNull($updatedUser->getProfilePicture());

        // Test serving
        $client->request('GET', '/api/user/profile-picture/'.$user->getId());
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function test_delete_me(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Delete Test Company '.uniqid());
        $entityManager->persist($company);

        // Case 1: User with courses (should be blocked)
        $trainer = new User();
        $trainer->setEmail('trainer_delete'.uniqid().'@example.com');
        $trainer->setName('Trainer Delete');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        $course = new \App\Entity\Course();
        $course->setTitle('Test Course');
        $course->setCapacity(10);
        $course->setStartTime(new \DateTime());
        $course->setEndTime(new \DateTime('+1 hour'));
        $course->setCompany($company);
        $trainer->addCourse($course);
        $entityManager->persist($course);
        $entityManager->flush();

        $tokenTrainer = $this->getToken($client, $trainer);
        $client->request('DELETE', '/api/user/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenTrainer,
        ]);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('active courses', $client->getResponse()->getContent());

        // Case 2: User without courses (should be allowed)
        $member = new User();
        $member->setEmail('member_delete'.uniqid().'@example.com');
        $member->setName('Member Delete');
        $member->setRoles(['ROLE_MEMBER']);
        $member->setPassword('password');
        $member->setIsVerified(true);
        $member->setCompany($company);
        $entityManager->persist($member);
        $entityManager->flush();

        $tokenMember = $this->getToken($client, $member);
        $client->request('DELETE', '/api/user/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenMember,
        ]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Verify user is gone
        $entityManager->clear();
        $deletedUser = $entityManager->getRepository(User::class)->find($member->getId());
        $this->assertNull($deletedUser);
    }

    public function test_update_notification_settings(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Test Company '.uniqid());
        $entityManager->persist($company);

        $trainer = new User();
        $trainer->setEmail('trainer'.uniqid().'@example.com');
        $trainer->setName('Test Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);
        $entityManager->flush();

        $token = $this->getToken($client, $trainer);

        // 1. Valid setting: None (0,0)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], json_encode([
            'courseStartNotificationHours' => 0,
            'courseStartNotificationMinutes' => 0,
        ]));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 2. Valid setting: 5 minutes (0,5)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], json_encode([
            'courseStartNotificationHours' => 0,
            'courseStartNotificationMinutes' => 5,
        ]));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 3. Valid setting: 1 hour (1,0)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], json_encode([
            'courseStartNotificationHours' => 1,
            'courseStartNotificationMinutes' => 0,
        ]));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 4. Invalid setting: 3 minutes (SHOULD BE REJECTED)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], json_encode([
            'courseStartNotificationHours' => 0,
            'courseStartNotificationMinutes' => 3,
        ]));
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // 5. Invalid setting: 7 minutes (not multiple of 5)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], json_encode([
            'courseStartNotificationHours' => 0,
            'courseStartNotificationMinutes' => 7,
        ]));
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    public function test_update_profile_gender_and_public(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Test Company Profile '.uniqid());
        $entityManager->persist($company);

        $user = new User();
        $user->setEmail('user_profile'.uniqid().'@example.com');
        $user->setName('Test User');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('password');
        $user->setIsVerified(true);
        $user->setCompany($company);
        $entityManager->persist($user);
        $entityManager->flush();

        $token = $this->getToken($client, $user);

        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], json_encode([
            'name' => 'Updated Name',
            'gender' => 'female',
            'isPublic' => true,
        ]));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entityManager->refresh($user);
        $this->assertEquals('Updated Name', $user->getName());
        $this->assertTrue($user->isPublic());
        $this->assertNotNull($user->getGender());
        $this->assertEquals('female', $user->getGender()->value);
    }

    public function test_delete_user_with_workout_records(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Delete Workout Test Company '.uniqid());
        $entityManager->persist($company);

        $user = new User();
        $user->setEmail('member_with_pbs'.uniqid().'@example.com');
        $user->setName('Member with PBs');
        $user->setRoles(['ROLE_MEMBER']);
        $user->setPassword('password');
        $user->setIsVerified(true);
        $user->setCompany($company);
        $entityManager->persist($user);

        // Add a workout record
        $record = new \App\Entity\UserWorkoutRecord();
        $record->setUser($user);
        $record->setExerciseName('Deadlift');
        $record->setWeightValue(100.0);
        $record->setDateAchieved(new \DateTime());
        $entityManager->persist($record);

        $entityManager->flush();

        $token = $this->getToken($client, $user);
        $userId = $user->getId();
        $recordId = $record->getId();

        $client->request('DELETE', '/api/user/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entityManager->clear();
        $deletedUser = $entityManager->getRepository(User::class)->find($userId);
        $this->assertNull($deletedUser);

        $deletedRecord = $entityManager->getRepository(\App\Entity\UserWorkoutRecord::class)->find($recordId);
        $this->assertNull($deletedRecord);
    }
}
