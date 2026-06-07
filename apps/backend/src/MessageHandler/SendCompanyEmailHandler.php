<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendCompanyEmailMessage;
use App\Repository\CompanyRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
class SendCompanyEmailHandler
{
    public function __construct(
        private readonly CompanyRepository $companyRepository,
        private readonly MailerInterface $defaultMailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'NO_REPLY_MAIL')]
        private readonly string $noReplyMail
    ) {
    }

    public function __invoke(SendCompanyEmailMessage $message): void
    {
        $company = $this->companyRepository->find($message->companyId);

        if (!$company) {
            $this->logger->error(sprintf('Company with ID %d not found for sending email.', $message->companyId));

            return;
        }

        $email = $message->email;

        if ($company->isCustomSmtpEnabled() && $this->hasValidSmtpSettings($company)) {
            $dsn = sprintf(
                'smtp://%s:%s@%s:%d',
                urlencode((string) $company->getSmtpUser()),
                urlencode((string) $company->getSmtpPassword()),
                $company->getSmtpHost(),
                $company->getSmtpPort()
            );

            if ($company->getSmtpEncryption()) {
                $dsn .= '?encryption='.$company->getSmtpEncryption();
            }

            try {
                $transport = Transport::fromDsn($dsn);
                $customMailer = new Mailer($transport);

                // Force from to match the custom SMTP user, preserving the display name if present
                $currentFrom = $email->getFrom()[0] ?? null;
                if ($currentFrom instanceof Address && $currentFrom->getName()) {
                    $email->from(new Address($company->getSmtpUser(), $currentFrom->getName()));
                } else {
                    $email->from($company->getSmtpUser());
                }

                $customMailer->send($email);

                return;
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Failed to send email via custom SMTP for company %d: %s. Falling back to default mailer.',
                    $company->getId(),
                    $e->getMessage()
                ));
            }
        }

        // Fallback to default mailer
        if (!$email->getFrom()) {
            $email->from($this->noReplyMail);
        }
        $this->defaultMailer->send($email);
    }

    private function hasValidSmtpSettings(\App\Entity\Company $company): bool
    {
        return (bool) ($company->getSmtpHost() && $company->getSmtpPort() && $company->getSmtpUser() && $company->getSmtpPassword());
    }
}
