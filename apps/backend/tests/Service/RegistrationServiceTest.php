<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\User;
use App\Repository\CompanyRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\PasswordValidator;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationServiceTest extends TestCase
{
    private $entityManager;
    private $passwordHasher;
    private $passwordValidator;
    private $translator;
    private $service;
    private $userRepository;
    private $companyRepository;
    private $security;
    private $emailService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->companyRepository = $this->createMock(CompanyRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);
        $this->passwordValidator = new PasswordValidator($this->translator);
        $this->security = $this->createMock(Security::class);
        $this->emailService = $this->createMock(EmailService::class);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Company::class, $this->companyRepository],
        ]);

        $this->service = new RegistrationService(
            $this->entityManager,
            $this->passwordHasher,
            $this->passwordValidator,
            $this->security,
            $this->emailService,
            $this->translator
        );
    }

    public function test_register_success(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User',
            'companyName' => 'New Company',
        ];

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->companyRepository->method('findOneBy')->willReturn(null); // New company
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->emailService->expects($this->once())->method('sendVerificationEmail');

        $user = $this->service->register($data);

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('Test User', $user->getName());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertTrue($user->isActive());
    }

    public function test_register_trial_member_success(): void
    {
        $data = [
            'email' => 'trial@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Trial User',
            'companyName' => 'Existing Company',
        ];

        $existingCompany = new Company();
        $existingCompany->setName('Existing Company');

        $admin = new User();
        $admin->setEmail('admin@example.com');

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->method('findByRole')->with('ROLE_ADMIN')->willReturn([$admin]);
        $this->companyRepository->method('findOneBy')->willReturn($existingCompany);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $this->emailService->expects($this->once())->method('sendVerificationEmail');
        $this->emailService->expects($this->once())->method('sendAdminNotificationEmail');

        $user = $this->service->register($data);

        $this->assertContains('ROLE_TRIAL', $user->getRoles());
        $this->assertTrue($user->isActive());
    }

    public function test_admin_register_success(): void
    {
        $data = [
            'email' => 'new-user@example.com',
            'password' => 'StrongPass123!',
            'name' => 'New User',
            'roles' => ['ROLE_TRAINER'],
        ];

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->emailService->expects($this->once())->method('sendVerificationEmail');

        $user = $this->service->register($data, true);

        $this->assertContains('ROLE_TRAINER', $user->getRoles());
        $this->assertTrue($user->isVerified());
    }

    public function test_register_role_restriction(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User',
            'companyName' => 'Existing Company',
            'role' => 'ROLE_ADMIN', // Should be ignored in public registration
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

    public function test_register_duplicate_email_throws_exception(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User',
        ];
        $this->userRepository->method('findOneBy')->willReturn(new User());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error.email_already_registered');

        $this->service->register($data);
    }

    public function test_verify_email_success(): void
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

    public function test_verify_email_expired_token_throws_exception(): void
    {
        $token = 'expired_token';
        $user = new User();
        $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt(new \DateTime('-1 hour'));

        $this->userRepository->method('findOneBy')->willReturn($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error.token_expired');

        $this->service->verifyEmail($token);
    }
}
