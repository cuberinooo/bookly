<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AdminSettings;
use App\Entity\Company;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AdminEmailSettingsTest extends WebTestCase
{
    private function createAdmin($entityManager): User
    {
        $suffix = uniqid();
        $company = new Company();
        $company->setName('Test Company '.$suffix);
        $entityManager->persist($company);

        $settings = new AdminSettings();
        $company->setAdminSettings($settings);
        $entityManager->persist($settings);

        $admin = new User();
        $admin->setEmail('admin_'.$suffix.'@example.com');
        $admin->setName('Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword('password');
        $admin->setIsVerified(true);
        $admin->setCompany($company);
        $entityManager->persist($admin);

        $entityManager->flush();

        return $admin;
    }

    private function getToken($client, User $user): string
    {
        return $client->getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    public function test_update_email_settings(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->createAdmin($entityManager);
        $token = $this->getToken($client, $admin);

        $client->request('PATCH', '/api/admin-settings', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], json_encode([
            'welcomeMailMarkdown' => 'Welcome member',
            'membershipWelcomeMailMarkdown' => 'Join us trial',
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entityManager->clear();
        $settings = $entityManager->getRepository(AdminSettings::class)->find($admin->getCompany()->getAdminSettings()->getId());
        $this->assertEquals('Welcome member', $settings->getWelcomeMailMarkdown());
        $this->assertEquals('Join us trial', $settings->getMembershipWelcomeMailMarkdown());
    }
}
