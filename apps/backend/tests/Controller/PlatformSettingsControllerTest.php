<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\PlatformSettings;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class PlatformSettingsControllerTest extends WebTestCase
{
    private $client;
    private $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->userRepository = static::getContainer()->get(UserRepository::class);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $settingsRepo = $entityManager->getRepository(PlatformSettings::class);
        $settings = $settingsRepo->findOneBy([]);
        if ($settings) {
            $entityManager->remove($settings);
            $entityManager->flush();
        }
    }

    private function getToken(User $user): string
    {
        return static::getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    private function createUser(string $email, array $roles): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $entityManager = static::getContainer()->get('doctrine')->getManager();

            $company = new Company();
            $company->setName('Test Company '.uniqid());

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

    public function test_public_get_platform_settings(): void
    {
        $this->client->request('GET', '/api/platform-settings');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('Kubilay Anil', $data['operatorName']);
        $this->assertEquals('Softwareentwickler', $data['profession']);
        $this->assertEquals('Deutschland', $data['country']);
    }

    public function test_monitor_access_controls(): void
    {
        // 1. Get requires ROLE_MONITOR
        $this->client->request('GET', '/api/monitor/platform-settings');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $admin = $this->createUser('admin_test_platform@example.com', ['ROLE_ADMIN']);
        $adminToken = $this->getToken($admin);

        $this->client->request('GET', '/api/monitor/platform-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adminToken,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $monitor = $this->createUser('monitor_test_platform@example.com', ['ROLE_MONITOR']);
        $monitorToken = $this->getToken($monitor);

        $this->client->request('GET', '/api/monitor/platform-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function test_monitor_update_settings(): void
    {
        $monitor = $this->createUser('monitor_test_platform@example.com', ['ROLE_MONITOR']);
        $monitorToken = $this->getToken($monitor);

        $updateData = [
            'operatorName' => 'New Name',
            'operatorCompany' => 'New Company',
            'operatorStreet' => 'New Street',
            'operatorHouseNumber' => '123',
            'operatorZipCode' => '12345',
            'operatorCity' => 'New City',
            'operatorEmail' => 'new@example.com',
            'operatorPhone' => '0987654321',
            'profession' => 'Designer',
            'country' => 'Austria',
            'taxId' => 'TAX-123',
            'vatId' => 'VAT-123',
        ];

        $this->client->request('PATCH', '/api/monitor/platform-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($updateData));

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('New Name', $data['operatorName']);
        $this->assertEquals('Designer', $data['profession']);
        $this->assertEquals('TAX-123', $data['taxId']);

        // Verify public endpoint gets updated data
        $this->client->request('GET', '/api/platform-settings');
        $this->assertResponseIsSuccessful();
        $publicData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('New Name', $publicData['operatorName']);
    }

    public function test_privacy_policy_upload_and_download(): void
    {
        $monitor = $this->createUser('monitor_test_platform@example.com', ['ROLE_MONITOR']);
        $monitorToken = $this->getToken($monitor);

        // Create a dummy pdf file
        $filePath = tempnam(sys_get_temp_dir(), 'privacy_pdf');
        file_put_contents($filePath, '%PDF-1.4 dummy content');
        $uploadedFile = new UploadedFile($filePath, 'privacy.pdf', 'application/pdf', null, true);

        try {
            // Test unauthorized upload
            $this->client->request('POST', '/api/monitor/platform-settings/privacy-policy', [], ['file' => $uploadedFile]);
            $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

            // Test authorized upload
            $this->client->request('POST', '/api/monitor/platform-settings/privacy-policy', [], ['file' => $uploadedFile], [
                'HTTP_AUTHORIZATION' => 'Bearer '.$monitorToken,
            ]);

            $this->assertResponseIsSuccessful();
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('path', $data);
            $this->assertStringContainsString('platform/legal/privacy-', $data['path']);

            // Test download public endpoint
            $this->client->request('GET', '/api/platform-settings/privacy-policy/download');
            $this->assertResponseIsSuccessful();
            $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
            $this->assertStringContainsString('dummy content', $this->client->getResponse()->getContent());

        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}
