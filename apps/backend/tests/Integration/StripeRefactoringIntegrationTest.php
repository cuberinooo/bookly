<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Company;
use App\Entity\StripeConfig;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StripeRefactoringIntegrationTest extends KernelTestCase
{
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testCompanyStripeConfigRelationship(): void
    {
        $uniqueName = 'Test Stripe Refactoring ' . uniqid();
        $company = new Company();
        $company->setName($uniqueName);
        
        // StripeConfig should be automatically initialized in Company constructor
        $stripeConfig = $company->getStripeConfig();
        $this->assertInstanceOf(StripeConfig::class, $stripeConfig);
        
        $stripeConfig->setStripeAccountId('acct_test123');
        $stripeConfig->setYearlyFeeEnabled(false);
        
        $this->entityManager->persist($company);
        $this->entityManager->flush();
        $this->entityManager->clear();
        
        $savedCompany = $this->entityManager->getRepository(Company::class)->findOneBy(['name' => $uniqueName]);
        $this->assertNotNull($savedCompany);
        $this->assertNotNull($savedCompany->getStripeConfig());
        $this->assertEquals('acct_test123', $savedCompany->getStripeConfig()->getStripeAccountId());
        $this->assertFalse($savedCompany->getStripeConfig()->isYearlyFeeEnabled());
    }

    public function testUserStripeCustomerId(): void
    {
        $uniqueEmail = 'paid_test_' . uniqid() . '@example.com';
        $user = new User();
        $user->setEmail($uniqueEmail);
        $user->setPassword('password');
        $user->setName('Paid User');
        $user->setStripeCustomerId('cus_test123');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->entityManager->clear();
        
        $savedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $uniqueEmail]);
        $this->assertEquals('cus_test123', $savedUser->getStripeCustomerId());
        
        $savedUser->setStripeCustomerId(null);
        $this->entityManager->flush();
        $this->entityManager->clear();
        
        $updatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $uniqueEmail]);
        $this->assertNull($updatedUser->getStripeCustomerId());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
