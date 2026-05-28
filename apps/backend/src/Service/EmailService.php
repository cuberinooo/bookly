<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Aws\S3\S3ClientInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private S3ClientInterface $s3Client,
        private string $s3Bucket
    ) {
    }

    public function sendVerificationEmail(User $user, bool $isAdminCreation = false, ?string $temporaryPassword = null): void
    {
        $company = $user->getCompany();
        $siteName = $company ? $company->getName() : 'Phoenix Athletics';

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $siteName))
            ->to($user->getEmail())
            ->subject(sprintf('Welcome to %s - Your Account is Ready', $siteName))
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'name' => $user->getName(),
                'siteName' => $siteName,
                'url' => $this->getVerificationUrl($user),
                'loginUrl' => $this->getLoginUrl(),
                'isAdminCreation' => $isAdminCreation,
                'temporaryPassword' => $temporaryPassword,
                'isVerified' => $user->isVerified(),
            ]);

        $this->mailer->send($email);
    }

    public function sendMembershipWelcomeEmail(User $user): void
    {
        $company = $user->getCompany();
        $settings = $company ? $company->getAdminSettings() : null;

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $company->getName()))
            ->to($user->getEmail());

        $markdown = $settings->getMembershipWelcomeMailMarkdown() ?? '';
        $siteName = $user->getCompany()->getName();
        $placeholders = [
            '{user_name}' => $user->getName(),
            '{company_name}' => $siteName,
        ];

        $content = str_replace(array_keys($placeholders), array_values($placeholders), $markdown);

        $email->subject(sprintf('Welcome to the community at %s!', $siteName))
            ->htmlTemplate('emails/company_welcome.html.twig')
            ->context([
                'content' => $content,
                'name' => $user->getName(),
                'siteName' => $siteName,
                'loginUrl' => $this->getLoginUrl(),
            ]);

        // Attach files
        $attachments = $settings->getMembershipWelcomeMailAttachments() ?? [];
        $this->attachFiles($email, $attachments);

        $this->mailer->send($email);
    }

    public function sendCompanySpecificWelcomeEmail(User $user): void
    {
        $company = $user->getCompany();
        $settings = $company ? $company->getAdminSettings() : null;

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $company->getName()))
            ->to($user->getEmail());

        $markdown = $settings->getWelcomeMailMarkdown() ?? '';
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
                'loginUrl' => $this->getLoginUrl(),
            ]);

        // Attach files
        $attachments = $settings->getWelcomeMailAttachments() ?? [];
        $this->attachFiles($email, $attachments);

        $this->mailer->send($email);
    }

    public function sendPriceChangeNotification(User $user, float $newPrice): void
    {
        $company = $user->getCompany();
        $siteName = $company ? $company->getName() : 'Phoenix Athletics';

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $siteName))
            ->to($user->getEmail())
            ->subject(sprintf('Important: Membership Price Update for %s', $siteName))
            ->htmlTemplate('emails/price_update.html.twig')
            ->context([
                'name' => $user->getName(),
                'siteName' => $siteName,
                'newPrice' => number_format($newPrice, 2, ',', '.') . ' €',
                'loginUrl' => $this->getLoginUrl(),
            ]);

        $this->mailer->send($email);
    }

    private function attachFiles(TemplatedEmail $email, array $attachments): void
    {
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
    }

    private function getVerificationUrl(User $user): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';

        return $frontendUrl.'/verify-email?token='.$user->getVerificationToken();
    }

    private function getLoginUrl(): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';

        return $frontendUrl.'/login';
    }
}
