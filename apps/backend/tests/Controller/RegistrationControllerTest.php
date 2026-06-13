<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AdminSettings;
use App\Entity\Company;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RegistrationControllerTest extends WebTestCase
{
    public function test_get_company_legal(): void
    {
        $client = static::createClient();
        static::getContainer()->get('limiter.company_check')->create('127.0.0.1')->reset();

        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $companyName = 'Test Legal Company '.uniqid();
        $company = new Company();
        $company->setName($companyName);

        $adminSettings = new AdminSettings();
        $adminSettings->setTermsAndConditionsMarkdown('# Terms');
        $company->setAdminSettings($adminSettings);

        $entityManager->persist($adminSettings);
        $entityManager->persist($company);
        $entityManager->flush();

        // Test found
        $client->request('GET', '/api/register/company-legal?name='.urlencode($companyName));
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

    public function test_get_terms_and_conditions(): void
    {
        $client = static::createClient();
        static::getContainer()->get('limiter.company_check')->create('127.0.0.1')->reset();

        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $companyName = 'Test Terms Company '.uniqid();
        $company = new Company();
        $company->setName($companyName);

        $adminSettings = new AdminSettings();
        $adminSettings->setTermsAndConditionsMarkdown('# Privacy Terms');
        $company->setAdminSettings($adminSettings);

        $entityManager->persist($adminSettings);
        $entityManager->persist($company);
        $entityManager->flush();

        // Test found
        $client->request('GET', '/api/register/terms-and-conditions?name='.urlencode($companyName));
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('# Privacy Terms', $data['termsAndConditionsMarkdown']);

        // Test not found
        $client->request('GET', '/api/register/terms-and-conditions?name=NonExistentCompany');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Test missing name
        $client->request('GET', '/api/register/terms-and-conditions');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_company_check_rate_limiting(): void
    {
        $client = static::createClient();
        static::getContainer()->get('limiter.company_check')->create('127.0.0.1')->reset();

        $limitReached = false;
        // Make up to 110 requests to trigger rate limit (limit is 100)
        for ($i = 0; $i < 110; ++$i) {
            $client->request('GET', '/api/register/company-legal?name=TestCompany');
            $status = $client->getResponse()->getStatusCode();
            if (Response::HTTP_TOO_MANY_REQUESTS === $status) {
                $limitReached = true;
                break;
            }
            $this->assertResponseIsSuccessful();
        }

        $this->assertTrue($limitReached, 'Rate limit was not triggered after 110 requests');
    }
}
