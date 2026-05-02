<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\AdminSettings;
use App\Repository\AdminSettingsRepository;
use App\Service\AdminSettingsService;
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
    private $uploadDir;
    private $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AdminSettingsRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->uploadDir = '/tmp/uploads';

        $this->service = new AdminSettingsService(
            $this->repository,
            $this->entityManager,
            $this->slugger,
            $this->uploadDir
        );
    }

    public function testGetSettingsByCompanyName(): void
    {
        $settings = new AdminSettings();
        $this->repository->expects($this->once())
            ->method('findOneByCompanyName')
            ->with('Test Company')
            ->willReturn($settings);

        $result = $this->service->getSettingsByCompanyName('Test Company');
        $this->assertSame($settings, $result);
    }

    public function testUpdateSettings(): void
    {
        $company = new Company();
        $company->setName('Old Name');
        $settings = $company->getAdminSettings();
        
        $this->entityManager->expects($this->once())->method('flush');

        $data = [
            'legalNoticeRepresentative' => 'John Doe',
            'legalNoticeMarkdown' => '# Test',
            'name' => 'New Company Name'
        ];

        $result = $this->service->updateSettings($company, $data);
        $this->assertSame($settings, $result);
        $this->assertEquals('John Doe', $result->getLegalNoticeRepresentative());
        $this->assertEquals('# Test', $result->getLegalNoticeMarkdown());
        $this->assertEquals('New Company Name', $company->getName());
    }

    public function testUploadPrivacyPolicy(): void
    {
        $company = new Company();
        $company->setName('Test Company');
        $settings = $company->getAdminSettings();
        
        $this->entityManager->expects($this->once())->method('flush');

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('test.pdf');
        $file->method('guessExtension')->willReturn('pdf');
        
        $this->slugger->method('slug')->willReturnMap([
            ['Test Company', new UnicodeString('test-company')],
            ['test', new UnicodeString('test')]
        ]);

        $file->expects($this->once())->method('move')->with(
            $this->uploadDir . '/test-company/legal',
            $this->callback(function($filename) {
                return str_starts_with($filename, 'test-');
            })
        );

        $result = $this->service->uploadPrivacyPolicy($company, $file);
        $this->assertStringStartsWith('/uploads/test-company/legal/test-', $result);
        $this->assertEquals($result, $settings->getPrivacyPolicyPdfPath());
    }
}
