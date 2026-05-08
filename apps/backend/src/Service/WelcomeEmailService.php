<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\AdminSettings;
use Aws\S3\S3ClientInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class WelcomeEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private S3ClientInterface $s3Client,
        private string $s3Bucket
    ) {}

    public function sendWelcomeEmail(User $user, bool $isNewCompanyCreator = false, ?string $temporaryPassword = null, bool $isAdminCreation = false): void
    {
        $company = $user->getCompany();
        $settings = $company ? $company->getAdminSettings() : null;

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $company->getName()))
            ->to($user->getEmail());

        if ($isNewCompanyCreator || !$settings || !$settings->getWelcomeMailMarkdown()) {
            // System Default Welcome Mail
            $this->sendSystemDefaultWelcomeEmail($email, $user, $temporaryPassword, $isAdminCreation);
        } else {
            // Company-Specific Welcome Mail
            $this->sendCompanySpecificWelcomeEmail($email, $user, $settings, $isAdminCreation, $temporaryPassword);
        }
    }

    private function sendSystemDefaultWelcomeEmail(TemplatedEmail $email, User $user, ?string $temporaryPassword, bool $isAdminCreation): void
    {
        $siteName = $user->getCompany() ? $user->getCompany()->getName() : 'Phoenix Athletics';

        $email->subject(sprintf('Welcome to %s - Your Account is Ready', $siteName))
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'name' => $user->getName(),
                'siteName' => $siteName,
                'url' => $this->getVerificationUrl($user),
                'loginUrl' => $this->getLoginUrl(),
                'isAdminCreation' => $isAdminCreation,
                'temporaryPassword' => $temporaryPassword,
            ]);

        $this->mailer->send($email);
    }

    private function sendCompanySpecificWelcomeEmail(TemplatedEmail $email, User $user, AdminSettings $settings, bool $isAdminCreation, ?string $temporaryPassword): void
    {
        $markdown = $settings->getWelcomeMailMarkdown();
        $siteName = $user->getCompany()->getName();
        $placeholders = [
            '{user_name}' => $user->getName(),
            '{company_name}' => $siteName,
        ];

        $content = str_replace(array_keys($placeholders), array_values($placeholders), $markdown);

        $email->subject(sprintf('Welcome to %s!', $siteName))
            ->htmlTemplate('emails/company_welcome.html.twig')
            ->context([
                'content' => $content,
                'name' => $user->getName(),
                'siteName' => $siteName,
                'isAdminCreation' => $isAdminCreation,
                'temporaryPassword' => $temporaryPassword,
                'verificationUrl' => $this->getVerificationUrl($user),
                'loginUrl' => $this->getLoginUrl(),
            ]);

        // Attach files
        $attachments = $settings->getWelcomeMailAttachments() ?? [];
        foreach ($attachments as $att) {
            try {
                $result = $this->s3Client->getObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $att['path'],
                ]);
                $email->attach($result['Body']->getContents(), $att['name']);
            } catch (\Exception $e) {
                // Log error but continue sending email without this attachment
            }
        }

        $this->mailer->send($email);
    }

    private function getVerificationUrl(User $user): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';
        return $frontendUrl . '/verify-email?token=' . $user->getVerificationToken();
    }

    private function getLoginUrl(): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';
        return $frontendUrl . '/login';
    }
}
