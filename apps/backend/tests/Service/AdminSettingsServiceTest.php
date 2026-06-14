<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AdminSettings;
use App\Entity\Company;
use App\Repository\AdminSettingsRepository;
use App\Service\AdminSettingsService;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class AdminSettingsServiceTest extends TestCase
{
    private $repository;
    private $entityManager;
    private $slugger;
    private $s3Client;
    private $mockHandler;
    private $s3Bucket;
    private $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AdminSettingsRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
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

        $this->service = new AdminSettingsService(
            $this->repository,
            $this->entityManager,
            $this->slugger,
            $this->s3Client,
            $this->s3Bucket
        );
    }

    public function test_get_settings_by_company_name(): void
    {
        $settings = new AdminSettings();
        $this->repository->expects($this->once())
            ->method('findOneByCompanyName')
            ->with('Test Company')
            ->willReturn($settings);

        $result = $this->service->getSettingsByCompanyName('Test Company');
        $this->assertSame($settings, $result);
    }

    public function test_update_settings(): void
    {
        $company = new Company();
        $company->setName('Old Name');
        $settings = $company->getAdminSettings();

        $this->entityManager->expects($this->once())->method('flush');

        $data = [
            'legalNoticeRepresentative' => 'John Doe',
            'legalNoticeMarkdown' => '# Test',
            'name' => 'New Company Name',
        ];

        $result = $this->service->updateSettings($company, $data);
        $this->assertSame($settings, $result);
        $this->assertEquals('John Doe', $result->getLegalNoticeRepresentative());
        $this->assertEquals('# Test', $result->getLegalNoticeMarkdown());
        $this->assertEquals('Old Name', $company->getName());
    }

    public function test_upload_privacy_policy(): void
    {
        $company = new Company();
        $company->setName('Test Company');
        $settings = $company->getAdminSettings();

        $this->entityManager->expects($this->once())->method('flush');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'test content');

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('test.pdf');
        $file->method('getClientOriginalExtension')->willReturn('pdf');
        $file->method('guessExtension')->willReturn('pdf');
        $file->method('getRealPath')->willReturn($tmpFile);

        $this->slugger->method('slug')->willReturnMap([
            ['Test Company', new UnicodeString('test-company')],
            ['test', new UnicodeString('test')],
        ]);

        $this->mockHandler->append(new Result(['ObjectURL' => 'https://example.com/test-bucket/test-company/legal/test-123.pdf']));

        try {
            $result = $this->service->uploadPrivacyPolicy($company, $file);

            $this->assertStringStartsWith('test-company/legal/test-legal', $result);
            $this->assertEquals($result, $settings->getPrivacyPolicyPdfPath());

            $lastCommand = $this->mockHandler->getLastCommand();
            $this->assertEquals('PutObject', $lastCommand->getName());
            $this->assertEquals('test-bucket', $lastCommand['Bucket']);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}
