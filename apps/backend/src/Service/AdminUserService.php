<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mime\Address;

class AdminUserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer
    ) {}

    public function resetPassword(User $user): string
    {
        $temporaryPassword = $this->generateRandomPassword();

        $hashedPassword = $this->passwordHasher->hashPassword($user, $temporaryPassword);
        $user->setPassword($hashedPassword);
        $user->setMustChangePassword(true);

        $this->entityManager->flush();

        $this->sendPasswordResetEmail($user, $temporaryPassword);

        return $temporaryPassword;
    }

    private function generateRandomPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        return substr(str_shuffle($chars), 0, $length);
    }

    private function sendPasswordResetEmail(User $user, string $temporaryPassword): void
    {
        $company = $user->getCompany();
        $siteName = $company ? $company->getName() : 'Bookly';

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $siteName))
            ->to($user->getEmail())
            ->subject('Account Security: Password Reset by Administrator')
            ->htmlTemplate('emails/admin_password_reset.html.twig')
            ->context([
                'name' => $user->getName(),
                'siteName' => $siteName,
                'temporaryPassword' => $temporaryPassword,
                'siteUrl' => $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200'
            ]);

        $this->mailer->send($email);
    }
}
