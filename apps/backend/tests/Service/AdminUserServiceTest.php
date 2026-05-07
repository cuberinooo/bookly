<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use App\Service\AdminUserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Common\Collections\ArrayCollection;

class AdminUserServiceTest extends TestCase
{
    private $entityManager;
    private $passwordHasher;
    private $mailer;
    private $filesystem;
    private $params;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->params->method('get')->with('upload_dir')->willReturn('/tmp/uploads');

        $this->service = new AdminUserService(
            $this->entityManager,
            $this->passwordHasher,
            $this->mailer,
            $this->filesystem,
            $this->params
        );
    }

    public function testDeleteUserSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCourses')->willReturn(new ArrayCollection());
        $user->method('getId')->willReturn(1);
        
        $company = $this->createMock(Company::class);
        $company->method('getName')->willReturn('Test Company');
        $user->method('getCompany')->willReturn($company);

        $this->entityManager->expects($this->once())->method('remove')->with($user);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->deleteUser($user);
        $this->assertTrue($result);
    }

    public function testDeleteUserBlocksIfHasCourses(): void
    {
        $user = $this->createMock(User::class);
        $course = $this->createMock(Course::class);
        $user->method('getCourses')->willReturn(new ArrayCollection([$course]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('active courses');

        $this->service->deleteUser($user, false);
    }

    public function testDeleteUserDeactivatesIfRequested(): void
    {
        $user = $this->createMock(User::class);
        $course = $this->createMock(Course::class);
        $user->method('getCourses')->willReturn(new ArrayCollection([$course]));

        $user->expects($this->once())->method('setIsActive')->with(false);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->deleteUser($user, true);
        $this->assertFalse($result);
    }
}
