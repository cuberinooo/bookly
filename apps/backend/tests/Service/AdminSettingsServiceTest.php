<?php

namespace App\Tests\Service;

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
    private $projectDir;
    private $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AdminSettingsRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->projectDir = '/tmp';

        $this->service = new AdminSettingsService(
            $this->repository,
            $this->entityManager,
            $this->slugger,
            $this->projectDir
        );
    }

    public function testGetSettings(): void
    {
        $settings = new AdminSettings();
        $this->repository->expects($this->once())->method('get')->willReturn($settings);

        $result = $this->service->getSettings();
        $this->assertSame($settings, $result);
    }

    public function testUpdateSettings(): void
    {
        $settings = new AdminSettings();
        $this->repository->expects($this->once())->method('get')->willReturn($settings);
        $this->entityManager->expects($this->once())->method('flush');

        $data = [
            'legalNoticeRepresentative' => 'John Doe',
            'legalNoticeMarkdown' => '# Test'
        ];

        $result = $this->service->updateSettings($data);
        $this->assertEquals('John Doe', $result->getLegalNoticeRepresentative());
        $this->assertEquals('# Test', $result->getLegalNoticeMarkdown());
    }

    public function testUploadPrivacyPolicy(): void
    {
        $settings = new AdminSettings();
        $this->repository->expects($this->once())->method('get')->willReturn($settings);
        $this->entityManager->expects($this->once())->method('flush');

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('test.pdf');
        $file->method('guessExtension')->willReturn('pdf');
        
        $this->slugger->method('slug')->willReturn(new UnicodeString('test'));

        $file->expects($this->once())->method('move')->with(
            $this->projectDir . '/public/uploads/legal',
            $this->callback(function($filename) {
                return str_starts_with($filename, 'test-');
            })
        );

        $result = $this->service->uploadPrivacyPolicy($file);
        $this->assertStringStartsWith('/uploads/legal/test-', $result);
        $this->assertEquals($result, $settings->getPrivacyPolicyPdfPath());
    }
}
