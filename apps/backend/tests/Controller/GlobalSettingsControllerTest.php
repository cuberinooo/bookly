<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GlobalSettingsControllerTest extends WebTestCase
{
    private $client;

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
        $company->setName('Global Settings Gym ' . $suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $settings->setMaxTrialBookingsPerClass(2);
        $entityManager->persist($settings);
        $company->setGlobalSettings($settings);

        $trainer = new User();
        $trainer->setEmail('trainer_global_settings_' . $suffix . '@example.com');
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
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
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
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
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
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $dataUpdated = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals(5, $dataUpdated['maxTrialBookingsPerClass']);
    }
}
