<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AdminSettings;
use App\Entity\Company;
use App\Entity\User;
use App\Service\EmailService;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class EmailServiceTest extends TestCase
{
    private $mailer;
    private $s3Client;
    private $service;
    private $s3Bucket = 'test-bucket';

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->s3Client = $this->createMock(S3Client::class);

        $this->service = new EmailService(
            $this->mailer,
            $this->s3Client,
            $this->s3Bucket
        );
    }

    public function test_send_welcome_email_for_member(): void
    {
        $company = new Company();
        $company->setName('Test Company');

        $settings = new AdminSettings();
        $settings->setWelcomeMailMarkdown('Welcome {user_name} to {company_name}');
        $settings->setWelcomeMailAttachments([['name' => 'contract.pdf', 'path' => 'path/to/contract.pdf']]);
        $company->setAdminSettings($settings);

        $user = new User();
        $user->setName('John Doe');
        $user->setEmail('john@example.com');
        $user->setRoles(['ROLE_MEMBER']);
        $user->setCompany($company);

        $this->s3Client->expects($this->once())
            ->method('__call')
            ->with('getObject', [[
                'Bucket' => $this->s3Bucket,
                'Key' => 'path/to/contract.pdf',
            ]])
            ->willReturn(['Body' => new class () {
                public function getContents()
                {
                    return 'pdf-content';
                }
            }]);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return 'Welcome to Test Company!' === $email->getSubject()
                       && 'Welcome John Doe to Test Company' === $email->getContext()['content']
                       && 1 === count($email->getAttachments());
            }));

        $this->service->sendCompanySpecificWelcomeEmail($user);
    }

    public function test_send_join_us_email_for_trial(): void
    {
        $company = new Company();
        $company->setName('Test Company');

        $settings = new AdminSettings();
        $settings->setJoinUsMailMarkdown('Join us {user_name} at {company_name}');
        $settings->setJoinUsMailAttachments([['name' => 'trial-info.pdf', 'path' => 'path/to/trial.pdf']]);
        $company->setAdminSettings($settings);

        $user = new User();
        $user->setName('Jane Doe');
        $user->setEmail('jane@example.com');
        $user->setRoles(['ROLE_TRIAL']);
        $user->setCompany($company);

        $this->s3Client->expects($this->once())
            ->method('__call')
            ->with('getObject', [[
                'Bucket' => $this->s3Bucket,
                'Key' => 'path/to/trial.pdf',
            ]])
            ->willReturn(['Body' => new class () {
                public function getContents()
                {
                    return 'pdf-content';
                }
            }]);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return 'Join us at Test Company!' === $email->getSubject()
                       && 'Join us Jane Doe at Test Company' === $email->getContext()['content']
                       && 1 === count($email->getAttachments());
            }));

        $this->service->sendTrialJoinUsEmail($user);
    }
}
