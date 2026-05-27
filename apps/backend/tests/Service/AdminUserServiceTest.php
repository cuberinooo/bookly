<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use App\Service\AdminUserService;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class AdminUserServiceTest extends TestCase
{
    private $entityManager;
    private $passwordHasher;
    private $mailer;
    private $slugger;
    private $s3Client;
    private $mockHandler;
    private $s3Bucket;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->slugger = $this->createMock(SluggerInterface::class);

        $this->mockHandler = new MockHandler();
        $this->s3Client = new S3Client([
            'region'  => 'us-east-1',
            'version' => 'latest',
            'handler' => $this->mockHandler,
            'credentials' => [
                'key'    => 'test-key',
                'secret' => 'test-secret',
            ],
        ]);
        $this->s3Bucket = 'test-bucket';

        $this->service = new AdminUserService(
            $this->entityManager,
            $this->passwordHasher,
            $this->mailer,
            $this->s3Client,
            $this->slugger,
            $this->s3Bucket
        );
    }

    public function test_delete_user_success(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCourses')->willReturn(new ArrayCollection());
        $user->method('getId')->willReturn(1);

        $company = $this->createMock(Company::class);
        $company->method('getName')->willReturn('Test Company');
        $user->method('getCompany')->willReturn($company);

        $this->slugger->method('slug')->with('Test Company')->willReturn(new UnicodeString('test-company'));

        // Mock ListObjectsV2 to return 0 objects
        $this->mockHandler->append(new Result([
            'IsTruncated' => false,
            'Contents' => [],
        ]));

        $this->entityManager->expects($this->once())->method('remove')->with($user);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->deleteUser($user);
        $this->assertTrue($result);

        $lastCommand = $this->mockHandler->getLastCommand();
        $this->assertStringContainsString('ListObjects', $lastCommand->getName());
        $this->assertEquals('test-bucket', $lastCommand['Bucket']);
        $this->assertEquals('test-company/1/', $lastCommand['Prefix']);
    }

    public function test_delete_user_blocks_if_has_courses(): void
    {
        $user = $this->createMock(User::class);
        $course = $this->createMock(Course::class);
        $user->method('getCourses')->willReturn(new ArrayCollection([$course]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('active courses');

        $this->service->deleteUser($user, false);
    }

    public function test_delete_user_deactivates_if_requested(): void
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
