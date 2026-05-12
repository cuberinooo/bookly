<?php

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\AdminSettings;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AdminEmailSettingsTest extends WebTestCase
{
    private function createAdmin($entityManager): User
    {
        $suffix = uniqid();
        $company = new Company();
        $company->setName('Test Company ' . $suffix);
        $entityManager->persist($company);

        $settings = new AdminSettings();
        $company->setAdminSettings($settings);
        $entityManager->persist($settings);

        $admin = new User();
        $admin->setEmail('admin_' . $suffix . '@example.com');
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

    public function testUpdateEmailSettings(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->createAdmin($entityManager);
        $token = $this->getToken($client, $admin);

        $client->request('PATCH', '/api/admin-settings', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ], json_encode([
            'welcomeMailMarkdown' => 'Welcome member',
            'joinUsMailMarkdown' => 'Join us trial'
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entityManager->clear();
        $settings = $entityManager->getRepository(AdminSettings::class)->find($admin->getCompany()->getAdminSettings()->getId());
        $this->assertEquals('Welcome member', $settings->getWelcomeMailMarkdown());
        $this->assertEquals('Join us trial', $settings->getJoinUsMailMarkdown());
    }

    public function testSendWelcomeMailToMemberAndTrial(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->createAdmin($entityManager);
        $token = $this->getToken($client, $admin);

        $suffix = uniqid();
        // Create a Trial User
        $trialUser = new User();
        $trialUser->setEmail('trial_' . $suffix . '@example.com');
        $trialUser->setName('Trial');
        $trialUser->setRoles(['ROLE_TRIAL']);
        $trialUser->setPassword('password');
        $trialUser->setCompany($admin->getCompany());
        $entityManager->persist($trialUser);

        // Create a Member
        $memberUser = new User();
        $memberUser->setEmail('member_' . $suffix . '@example.com');
        $memberUser->setName('Member');
        $memberUser->setRoles(['ROLE_MEMBER']);
        $memberUser->setPassword('password');
        $memberUser->setCompany($admin->getCompany());
        $entityManager->persist($memberUser);

        $entityManager->flush();

        // 1. Send to Trial
        $client->request('POST', '/api/admin/users/' . $trialUser->getId() . '/send-join-us', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 2. Send to Member
        $client->request('POST', '/api/admin/users/' . $memberUser->getId() . '/send-join-us', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entityManager->clear();
        $this->assertTrue($entityManager->getRepository(User::class)->find($trialUser->getId())->isJoinUsMailSent());
        $this->assertTrue($entityManager->getRepository(User::class)->find($memberUser->getId())->isJoinUsMailSent());
    }
}
