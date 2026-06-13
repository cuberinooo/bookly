<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Company;
use App\Message\SendCompanyEmailMessage;
use App\MessageHandler\SendCompanyEmailHandler;
use App\Repository\CompanyRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SendCompanyEmailHandlerTest extends TestCase
{
    private $companyRepository;
    private $defaultMailer;
    private $logger;
    private $handler;
    private string $noReplyMail = 'noreply@example.com';

    protected function setUp(): void
    {
        $this->companyRepository = $this->createMock(CompanyRepository::class);
        $this->defaultMailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SendCompanyEmailHandler(
            $this->companyRepository,
            $this->defaultMailer,
            $this->logger,
            $this->noReplyMail
        );
    }

    public function test_fallback_to_default_mailer_when_custom_smtp_disabled(): void
    {
        $companyId = 1;
        $company = $this->createMock(Company::class);
        $company->method('isCustomSmtpEnabled')->willReturn(false);
        $company->method('getId')->willReturn($companyId);

        $this->companyRepository->expects($this->once())
            ->method('find')
            ->with($companyId)
            ->willReturn($company);

        $email = (new Email())
            ->to('recipient@example.com')
            ->subject('Subject')
            ->text('Content');

        $this->defaultMailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $sentEmail) {
                return 'recipient@example.com' === $sentEmail->getTo()[0]->getAddress()
                    && $sentEmail->getFrom()[0]->getAddress() === $this->noReplyMail;
            }));

        $message = new SendCompanyEmailMessage($companyId, $email);
        ($this->handler)($message);
    }

    public function test_fallback_to_default_mailer_when_smtp_settings_invalid(): void
    {
        $companyId = 1;
        $company = $this->createMock(Company::class);
        $company->method('isCustomSmtpEnabled')->willReturn(true);
        $company->method('getSmtpHost')->willReturn(null); // Invalid
        $company->method('getId')->willReturn($companyId);

        $this->companyRepository->expects($this->once())
            ->method('find')
            ->with($companyId)
            ->willReturn($company);

        $email = (new Email())->to('recipient@example.com');

        $this->defaultMailer->expects($this->once())
            ->method('send');

        $message = new SendCompanyEmailMessage($companyId, $email);
        ($this->handler)($message);
    }

    public function test_custom_smtp_fallback_on_failure(): void
    {
        $companyId = 1;
        $company = $this->createMock(Company::class);
        $company->method('isCustomSmtpEnabled')->willReturn(true);
        $company->method('getSmtpHost')->willReturn('smtp.invalid');
        $company->method('getSmtpPort')->willReturn(587);
        $company->method('getSmtpUser')->willReturn('user');
        $company->method('getSmtpPassword')->willReturn('pass');
        $company->method('getSmtpEncryption')->willReturn(null);
        $company->method('getId')->willReturn($companyId);

        $this->companyRepository->expects($this->once())
            ->method('find')
            ->with($companyId)
            ->willReturn($company);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to send email via custom SMTP'));

        $this->defaultMailer->expects($this->once())
            ->method('send');

        $email = (new Email())->to('recipient@example.com');
        $message = new SendCompanyEmailMessage($companyId, $email);

        ($this->handler)($message);
    }

    public function test_custom_smtp_forces_from_address_to_match_smtp_user(): void
    {
        $companyId = 1;
        $company = $this->createMock(Company::class);
        $company->method('isCustomSmtpEnabled')->willReturn(true);
        $company->method('getSmtpHost')->willReturn('smtp.invalid');
        $company->method('getSmtpPort')->willReturn(587);
        $company->method('getSmtpUser')->willReturn('custom-smtp@company.com');
        $company->method('getSmtpPassword')->willReturn('pass');
        $company->method('getSmtpEncryption')->willReturn(null);
        $company->method('getId')->willReturn($companyId);

        $this->companyRepository->expects($this->once())
            ->method('find')
            ->with($companyId)
            ->willReturn($company);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to send email via custom SMTP'));

        $this->defaultMailer->expects($this->once())
            ->method('send');

        $email = (new Email())
            ->from('noreply@example.com')
            ->to('recipient@example.com');

        $message = new SendCompanyEmailMessage($companyId, $email);

        ($this->handler)($message);

        $this->assertCount(1, $email->getFrom());
        $this->assertSame('custom-smtp@company.com', $email->getFrom()[0]->getAddress());
    }
}
