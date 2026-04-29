<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LegalSettingsControllerTest extends WebTestCase
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
        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.com']);
        if (!$admin) {
            $admin = new User();
            $admin->setEmail('admin@example.com');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword('password');
            $admin->setName('Admin');
            $admin->setIsVerified(true);
            $admin->setIsActive(true);
            static::getContainer()->get('doctrine')->getManager()->persist($admin);
            static::getContainer()->get('doctrine')->getManager()->flush();
        }
        return $admin;
    }

    public function testGetSettingsIsPublic(): void
    {
        $this->client->request('GET', '/api/legal-settings');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testUpdateSettingsRequiresAdmin(): void
    {
        $this->client->request('PATCH', '/api/legal-settings', [], [], [], json_encode(['legalNoticeCompanyName' => 'Denied']));
        $this->assertResponseStatusCodeSame(401);

        $admin = $this->createAdmin();
        $token = $this->getToken($admin);

        $this->client->request('PATCH', '/api/legal-settings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['legalNoticeCompanyName' => 'Allowed']));
        
        $this->assertResponseIsSuccessful();
    }
}
