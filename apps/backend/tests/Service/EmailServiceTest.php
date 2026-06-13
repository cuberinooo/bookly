<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AdminSettings;
use App\Entity\Company;
use App\Entity\User;
use App\Message\SendCompanyEmailMessage;
use App\Service\EmailService;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\BodyRendererInterface;

class EmailServiceTest extends TestCase
{
    private $bus;
    private $bodyRenderer;
    private $s3Client;
    private $translator;
    private $service;
    private $s3Bucket = 'test-bucket';

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->bodyRenderer = $this->createMock(BodyRendererInterface::class);
        $this->s3Client = $this->createMock(S3Client::class);
        $this->translator = $this->createMock(\Symfony\Contracts\Translation\TranslatorInterface::class);

        $this->service = new EmailService(
            $this->bus,
            $this->bodyRenderer,
            $this->s3Client,
            $this->s3Bucket,
            $this->translator
        );
    }

    public function test_send_welcome_email_for_member(): void
    {
        $companyId = 123;
        $company = new Company();
        $company->setName('Test Company');
        // We need to set the ID, but it's private and usually managed by Doctrine.
        // We'll use reflection or just assume the repository mock in a real scenario.
        // For this test, let's mock the Company to return an ID.
        $companyMock = $this->createMock(Company::class);
        $companyMock->method('getId')->willReturn($companyId);
        $companyMock->method('getName')->willReturn('Test Company');

        $settings = new AdminSettings();
        $settings->setWelcomeMailMarkdown('Welcome {user_name} to {company_name}');
        $settings->setWelcomeMailAttachments([['name' => 'contract.pdf', 'path' => 'path/to/contract.pdf']]);
        $companyMock->method('getAdminSettings')->willReturn($settings);

        $user = new User();
        $user->setName('John Doe');
        $user->setEmail('john@example.com');
        $user->setRoles(['ROLE_MEMBER']);
        $user->setCompany($companyMock);

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

        $this->bodyRenderer->expects($this->once())
            ->method('render');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendCompanyEmailMessage $message) use ($companyId) {
                $email = $message->email;

                return $message->companyId === $companyId
                       && $email instanceof TemplatedEmail
                       && 'Welcome to Test Company!' === $email->getSubject()
                       && 'Welcome John Doe to Test Company' === $email->getContext()['content']
                       && 1 === count($email->getAttachments());
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->sendCompanySpecificWelcomeEmail($user);
    }

    public function test_send_join_us_email_for_trial(): void
    {
        $companyId = 456;
        $companyMock = $this->createMock(Company::class);
        $companyMock->method('getId')->willReturn($companyId);
        $companyMock->method('getName')->willReturn('Test Company');

        $settings = new AdminSettings();
        $settings->setMembershipWelcomeMailMarkdown('Join us {user_name} at {company_name}');
        $settings->setMembershipWelcomeMailAttachments([['name' => 'trial-info.pdf', 'path' => 'path/to/trial.pdf']]);
        $companyMock->method('getAdminSettings')->willReturn($settings);

        $user = new User();
        $user->setName('Jane Doe');
        $user->setEmail('jane@example.com');
        $user->setRoles(['ROLE_TRIAL']);
        $user->setCompany($companyMock);

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

        $this->bodyRenderer->expects($this->once())
            ->method('render');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendCompanyEmailMessage $message) use ($companyId) {
                $email = $message->email;

                return $message->companyId === $companyId
                       && $email instanceof TemplatedEmail
                       && 'Join us at Test Company!' === $email->getSubject()
                       && 'Join us Jane Doe at Test Company' === $email->getContext()['content']
                       && 1 === count($email->getAttachments());
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->sendMembershipWelcomeEmail($user);
    }

    public function test_send_payment_failed_email(): void
    {
        $companyId = 789;
        $companyMock = $this->createMock(Company::class);
        $companyMock->method('getId')->willReturn($companyId);
        $companyMock->method('getName')->willReturn('Test Gym');

        $user = new User();
        $user->setName('Bob Miller');
        $user->setEmail('bob@example.com');
        $user->setCompany($companyMock);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('email.payment_failed.subject', ['%siteName%' => 'Test Gym'])
            ->willReturn('Action Required: Payment Failed for Test Gym');

        $this->bodyRenderer->expects($this->once())
            ->method('render');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendCompanyEmailMessage $message) use ($companyId) {
                $email = $message->email;

                return $message->companyId === $companyId
                       && $email instanceof TemplatedEmail
                       && 'Action Required: Payment Failed for Test Gym' === $email->getSubject()
                       && 'Bob Miller' === $email->getContext()['name']
                       && 'Test Gym' === $email->getContext()['siteName'];
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->sendPaymentFailedEmail($user);
    }
}
