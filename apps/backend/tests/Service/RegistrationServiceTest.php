<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RegistrationService;
use App\Service\PasswordValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationServiceTest extends TestCase
{
    private $entityManager;
    private $passwordHasher;
    private $mailer;
    private $passwordValidator;
    private $service;
    private $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordValidator = new PasswordValidator();

        $this->entityManager->method('getRepository')->with(User::class)->willReturn($this->userRepository);

        $this->service = new RegistrationService(
            $this->entityManager, 
            $this->passwordHasher, 
            $this->mailer, 
            $this->passwordValidator
        );
    }

    public function testRegisterSuccess(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User',
            'role' => 'ROLE_MEMBER'
        ];

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->method('findByRole')->with('ROLE_ADMIN')->willReturn([]);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        // Expect 2 emails: verification email to user and notification email to admin
        $this->mailer->expects($this->exactly(2))->method('send');

        $user = $this->service->register($data);

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('Test User', $user->getName());
        $this->assertEquals('hashed_password', $user->getPassword());
        $this->assertContains('ROLE_MEMBER', $user->getRoles());
        $this->assertFalse($user->isVerified());
        $this->assertFalse($user->isActive());
        $this->assertNotNull($user->getVerificationToken());
    }

    public function testAdminRegisterSuccess(): void
    {
        $data = [
            'email' => 'new-user@example.com',
            'password' => 'StrongPass123!',
            'name' => 'New User',
            'role' => 'ROLE_TRAINER'
        ];

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        // Expect only 1 email: verification email to user (no admin notification for admin creation)
        $this->mailer->expects($this->once())->method('send');

        $user = $this->service->register($data, true);

        $this->assertTrue($user->isVerified());
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->isMustChangePassword());
    }

    public function testRegisterRoleRestriction(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'StrongPass123!',
            'name' => 'Test User',
            'role' => 'ROLE_ADMIN'
        ];

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->method('findByRole')->willReturn([]);
        
        $user = $this->service->register($data);

        // ROLE_ADMIN should be downgraded to ROLE_MEMBER
        $this->assertContains('ROLE_MEMBER', $user->getRoles());
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
