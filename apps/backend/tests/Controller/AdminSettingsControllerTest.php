<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class AdminSettingsControllerTest extends WebTestCase
{
    private $client;
    private $userRepository;

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

    private function createAdmin(): User
    {
        $admin = $this->userRepository->findOneBy(['email' => 'admin_settings@example.com']);
        if (!$admin) {
            $entityManager = static::getContainer()->get('doctrine')->getManager();

            $company = new Company();
            $company->setName('Test Company Settings');

            $entityManager->persist($company);

            $admin = new User();
            $admin->setEmail('admin_settings@example.com');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword('password');
            $admin->setName('Admin');
            $admin->setIsVerified(true);
            $admin->setIsActive(true);
            $admin->setCompany($company);

            $entityManager->persist($admin);
            $entityManager->flush();
        }

        return $admin;
    }

    private function createAnotherAdmin(): User
    {
        $admin = $this->userRepository->findOneBy(['email' => 'another_admin@example.com']);
        if (!$admin) {
            $entityManager = static::getContainer()->get('doctrine')->getManager();

            $company = new Company();
            $company->setName('Another Company Settings');

            $entityManager->persist($company);

            $admin = new User();
            $admin->setEmail('another_admin@example.com');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword('password');
            $admin->setName('Another Admin');
            $admin->setIsVerified(true);
            $admin->setIsActive(true);
            $admin->setCompany($company);

            $entityManager->persist($admin);
            $entityManager->flush();
        }

        return $admin;
    }

    public function test_get_settings_requires_auth(): void
    {
        // Security layer returns 401 if not authenticated
        $this->client->request('GET', '/api/admin-settings');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $admin = $this->createAdmin();
        $token = $this->getToken($admin);

        $this->client->request('GET', '/api/admin-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function test_update_settings_requires_admin(): void
    {
        $this->client->request('PATCH', '/api/admin-settings', [], [], [], json_encode(['legalNoticeRepresentative' => 'Denied']));
        $this->assertResponseStatusCodeSame(401);

        $admin = $this->createAdmin();
        $token = $this->getToken($admin);

        $this->client->request('PATCH', '/api/admin-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['legalNoticeRepresentative' => 'John Doe']));

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Admin settings updated', $data['status']);
    }

    public function test_admin_cannot_update_company_name(): void
    {
        $admin = $this->createAdmin();
        $token = $this->getToken($admin);
        $originalName = $admin->getCompany()->getName();
        $newName = 'Hacker Company';

        $this->client->request('PATCH', '/api/admin-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => $newName]));

        $this->assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $company = $entityManager->getRepository(Company::class)->find($admin->getCompany()->getId());

        $this->assertEquals($originalName, $company->getName(), 'Admin should not be able to change company name');
    }

    public function test_download_privacy_policy(): void
    {
        $admin = $this->createAdmin();
        $companyName = $admin->getCompany()->getName();

        // Settings exist but path is null
        $this->client->request('GET', '/api/admin-settings/privacy-policy/download?companyName='.urlencode($companyName));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Non-existent company
        $this->client->request('GET', '/api/admin-settings/privacy-policy/download?companyName=NonExistent');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Missing company name
        $this->client->request('GET', '/api/admin-settings/privacy-policy/download');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_company_logo_upload_and_delete(): void
    {
        $admin = $this->createAdmin();
        $token = $this->getToken($admin);

        // Create a dummy image file
        $filePath = tempnam(sys_get_temp_dir(), 'logo_img');
        file_put_contents($filePath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true));
        $uploadedFile = new UploadedFile($filePath, 'logo.gif', 'image/gif', null, true);

        try {
            // Test unauthorized upload
            $this->client->request('POST', '/api/admin-settings/company-logo', [], ['file' => $uploadedFile]);
            $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

            // Test authorized upload
            $this->client->request('POST', '/api/admin-settings/company-logo', [], ['file' => $uploadedFile], [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);

            $this->assertResponseIsSuccessful();
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('path', $data);
            $this->assertStringContainsString('logo/logo_', $data['path']);

            // Verify settings updated in DB
            $entityManager = static::getContainer()->get('doctrine')->getManager();
            $entityManager->clear();
            $company = $entityManager->getRepository(Company::class)->find($admin->getCompany()->getId());
            $this->assertNotNull($company->getAdminSettings()->getCompanyLogoPath());
            $this->assertEquals($data['path'], $company->getAdminSettings()->getCompanyLogoPath());

            // Test serving logo via uploads WITHOUT a token (should return 401)
            $this->client->request('GET', '/uploads/'.$data['path']);
            $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

            // Test serving logo via uploads WITH correct token in Authorization header (should succeed)
            $this->client->request('GET', '/uploads/'.$data['path'], [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);
            $this->assertResponseIsSuccessful();
            $this->assertResponseHeaderSame('Content-Type', 'image/gif');

            // Test serving logo via uploads WITH correct token in query parameter (should succeed)
            $this->client->request('GET', '/uploads/'.$data['path'].'?token='.urlencode($token));
            $this->assertResponseIsSuccessful();
            $this->assertResponseHeaderSame('Content-Type', 'image/gif');

            // Test serving logo via uploads WITH token from a DIFFERENT company (should return 403)
            $anotherAdmin = $this->createAnotherAdmin();
            $anotherToken = $this->getToken($anotherAdmin);
            $this->client->request('GET', '/uploads/'.$data['path'].'?token='.urlencode($anotherToken));
            $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

            // Test delete logo
            $this->client->request('DELETE', '/api/admin-settings/company-logo', [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);
            $this->assertResponseIsSuccessful();
            $deleteData = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertEquals('Company logo deleted', $deleteData['status']);

            // Verify settings updated in DB after delete
            $entityManager->clear();
            $companyAfterDelete = $entityManager->getRepository(Company::class)->find($admin->getCompany()->getId());
            $this->assertNull($companyAfterDelete->getAdminSettings()->getCompanyLogoPath());

            // Test serving logo via uploads after deletion (should return 404)
            $this->client->request('GET', '/uploads/'.$data['path'].'?token='.urlencode($token));
            $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        } finally {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }
}
