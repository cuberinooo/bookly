<?php

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserControllerTest extends WebTestCase
{
    private function getToken($client, User $user): string
    {
        return $client->getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    public function testProfilePictureUpload(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Upload Test Company ' . uniqid());
        $entityManager->persist($company);

        $user = new User();
        $user->setEmail('uploader' . uniqid() . '@example.com');
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
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('profilePicture', $data);

        // Verify entity updated
        $entityManager->clear();
        $updatedUser = $entityManager->getRepository(User::class)->find($user->getId());
        $this->assertNotNull($updatedUser->getProfilePicture());

        // Test serving
        $client->request('GET', '/api/user/profile-picture/' . $user->getId());
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testUpdateNotificationSettings(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Test Company ' . uniqid());
        $entityManager->persist($company);

        $trainer = new User();
        $trainer->setEmail('trainer' . uniqid() . '@example.com');
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
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ], json_encode([
            'courseStartNotificationHours' => 0,
            'courseStartNotificationMinutes' => 0
        ]));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 2. Valid setting: 5 minutes (0,5)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ], json_encode([
            'courseStartNotificationHours' => 0,
            'courseStartNotificationMinutes' => 5
        ]));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 3. Valid setting: 1 hour (1,0)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ], json_encode([
            'courseStartNotificationHours' => 1,
            'courseStartNotificationMinutes' => 0
        ]));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 4. Invalid setting: 3 minutes (SHOULD BE REJECTED)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ], json_encode([
            'courseStartNotificationHours' => 0,
            'courseStartNotificationMinutes' => 3
        ]));
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // 5. Invalid setting: 7 minutes (not multiple of 5)
        $client->request('PATCH', '/api/user/me', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ], json_encode([
            'courseStartNotificationHours' => 0,
            'courseStartNotificationMinutes' => 7
        ]));
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }
}
