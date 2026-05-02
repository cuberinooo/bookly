<?php

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\AdminSettings;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RegistrationControllerTest extends WebTestCase
{
    public function testGetCompanyLegal(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $companyName = 'Test Legal Company ' . uniqid();
        $company = new Company();
        $company->setName($companyName);

        $adminSettings = new AdminSettings();
        $adminSettings->setTermsAndConditionsMarkdown('# Terms');
        $company->setAdminSettings($adminSettings);

        $entityManager->persist($adminSettings);
        $entityManager->persist($company);
        $entityManager->flush();

        // Test found
        $client->request('GET', '/api/register/company-legal?name=' . urlencode($companyName));
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['found']);
        $this->assertEquals('# Terms', $data['termsAndConditionsMarkdown']);

        // Test not found
        $client->request('GET', '/api/register/company-legal?name=NonExistentCompany');
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['found']);

        // Test missing name
        $client->request('GET', '/api/register/company-legal');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
