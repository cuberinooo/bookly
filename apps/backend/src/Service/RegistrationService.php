<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private PasswordValidator $passwordValidator
    ) {
    }

    public function register(array $data): User
    {
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            throw new \Exception('Email already registered');
        }

        $password = $data['password'] ?? '';
        $this->passwordValidator->validate($password);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $role = $data['role'] ?? 'ROLE_MEMBER';
        if (!in_array($role, ['ROLE_MEMBER', 'ROLE_TRAINER'])) {
            $role = 'ROLE_MEMBER';
        }
        $user->setRoles([$role]);
        $user->setIsVerified(false);

        $this->generateVerificationToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->sendVerificationEmail($user);

        return $user;
    }

    public function verifyEmail(string $token): void
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            throw new \Exception('Invalid token');
        }

        if ($user->getVerificationTokenExpiresAt() < new \DateTime()) {
            throw new \Exception('Token expired');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $this->entityManager->flush();
    }

    public function resendVerification(string $email): void
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || $user->isVerified()) {
            return;
        }

        $this->generateVerificationToken($user);
        $this->entityManager->flush();

        $this->sendVerificationEmail($user);
    }

    private function generateVerificationToken(User $user): void
    {
        $user->setVerificationToken(Uuid::v4()->toBase58());
        $user->setVerificationTokenExpiresAt(new \DateTime('+24 hours'));
    }

    private function sendVerificationEmail(User $user): void
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';
        $verificationUrl = $frontendUrl . '/verify-email?token=' . $user->getVerificationToken();

        $email = (new TemplatedEmail())
            ->from($_ENV['NO_REPLAY_MAIL'] ?? 'noreply@example.com')
            ->to($user->getEmail())
            ->subject('Verify your Phoenix Booking account')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'name' => $user->getName(),
                'url' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }
}
