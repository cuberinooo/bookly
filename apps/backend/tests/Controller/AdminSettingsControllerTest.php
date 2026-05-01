<?php

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\AdminSettings;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
            
            $adminSettings = new AdminSettings();
            $company->setAdminSettings($adminSettings);
            
            $globalSettings = new GlobalSettings();
            $company->setGlobalSettings($globalSettings);
            
            $entityManager->persist($company);
            $entityManager->persist($adminSettings);
            $entityManager->persist($globalSettings);

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

    public function testGetSettingsRequiresAuth(): void
    {
        // Security layer returns 401 if not authenticated
        $this->client->request('GET', '/api/admin-settings');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        
        $admin = $this->createAdmin();
        $token = $this->getToken($admin);
        
        $this->client->request('GET', '/api/admin-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testUpdateSettingsRequiresAdmin(): void
    {
        $this->client->request('PATCH', '/api/admin-settings', [], [], [], json_encode(['legalNoticeRepresentative' => 'Denied']));
        $this->assertResponseStatusCodeSame(401);

        $admin = $this->createAdmin();
        $token = $this->getToken($admin);

        $this->client->request('PATCH', '/api/admin-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['legalNoticeRepresentative' => 'John Doe']));
        
        $this->assertResponseIsSuccessful();
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Admin settings updated', $data['status']);
    }
}
