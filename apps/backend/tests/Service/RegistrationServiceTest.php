<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Company;
use App\Repository\UserRepository;
use App\Repository\CompanyRepository;
use App\Repository\AdminSettingsRepository;
use App\Service\RegistrationService;
use App\Service\PasswordValidator;
use App\Service\WelcomeEmailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;

class RegistrationServiceTest extends TestCase
{
    private $entityManager;
    private $passwordHasher;
    private $mailer;
    private $passwordValidator;
    private $service;
    private $userRepository;
    private $companyRepository;
    private $adminSettingsRepository;
    private $security;
    private $welcomeEmailService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->companyRepository = $this->createMock(CompanyRepository::class);
        $this->passwordValidator = new PasswordValidator();
        $this->adminSettingsRepository = $this->createMock(AdminSettingsRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->welcomeEmailService = $this->createMock(WelcomeEmailService::class);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Company::class, $this->companyRepository],
        ]);

        $this->service = new RegistrationService(
            $this->entityManager, 
            $this->passwordHasher, 
            $this->mailer, 
            $this->passwordValidator,
            $this->adminSettingsRepository,
            $this->security,
            $this->welcomeEmailService
        );
    }

    public function testRegisterSuccess(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User',
            'companyName' => 'New Company'
        ];

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->method('findByRole')->with('ROLE_ADMIN')->willReturn([]);
        $this->companyRepository->method('findOneBy')->willReturn(null); // New company
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->welcomeEmailService->expects($this->once())->method('sendWelcomeEmail');

        $user = $this->service->register($data);

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('Test User', $user->getName());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testRegisterTrialMemberSuccess(): void
    {
        $data = [
            'email' => 'trial@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Trial User',
            'companyName' => 'Existing Company'
        ];

        $existingCompany = new Company();
        $existingCompany->setName('Existing Company');

        $admin = new User();
        $admin->setEmail('admin@example.com');

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->method('findByRole')->with('ROLE_ADMIN')->willReturn([$admin]);
        $this->companyRepository->method('findOneBy')->willReturn($existingCompany);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $this->welcomeEmailService->expects($this->once())->method('sendWelcomeEmail');
        $this->mailer->expects($this->once())->method('send'); // Admin notification

        $user = $this->service->register($data);

        $this->assertContains('ROLE_TRIAL', $user->getRoles());
    }

    public function testAdminRegisterSuccess(): void
    {
        $data = [
            'email' => 'new-user@example.com',
            'password' => 'StrongPass123!',
            'name' => 'New User',
            'roles' => ['ROLE_TRAINER']
        ];

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->welcomeEmailService->expects($this->once())->method('sendWelcomeEmail');

        $user = $this->service->register($data, true);

        $this->assertContains('ROLE_TRAINER', $user->getRoles());
        $this->assertTrue($user->isVerified());
    }

    public function testRegisterRoleRestriction(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User',
            'companyName' => 'Existing Company',
            'role' => 'ROLE_ADMIN' // Should be ignored in public registration
        ];

        $existingCompany = new Company();
        $existingCompany->setName('Existing Company');
        
        $admin = new User();
        $admin->setEmail('admin@example.com');

        $this->companyRepository->method('findOneBy')->willReturn($existingCompany);
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->method('findByRole')->willReturn([$admin]);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');
        
        $user = $this->service->register($data);

        // Should be ROLE_TRIAL, not ROLE_ADMIN
        $this->assertContains('ROLE_TRIAL', $user->getRoles());
        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testRegisterDuplicateEmailThrowsException(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User'
        ];
        $this->userRepository->method('findOneBy')->willReturn(new User());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email already registered');

        $this->service->register($data);
    }

    public function testVerifyEmailSuccess(): void
    {
        $token = 'valid_token';
        $user = new User();
        $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt(new \DateTime('+1 hour'));

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->verifyEmail($token);

        $this->assertTrue($user->isVerified());
        $this->assertNull($user->getVerificationToken());
    }

    public function testVerifyEmailExpiredTokenThrowsException(): void
    {
        $token = 'expired_token';
        $user = new User();
        $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt(new \DateTime('-1 hour'));

        $this->userRepository->method('findOneBy')->willReturn($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token expired');

        $this->service->verifyEmail($token);
    }
}
