<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;

class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private PasswordValidator $passwordValidator,
        private \App\Repository\AdminSettingsRepository $adminSettingsRepository,
        private Security $security,
        private WelcomeEmailService $welcomeEmailService
    ) {
    }

    public function register(array $data, bool $isAdminCreation = false): User
    {
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            throw new \Exception('Email already registered');
        }

        $password = $data['password'] ?? '';
        $this->passwordValidator->validate($password);

        $currentUser = $this->security->getUser();
        $defaultCompanyName = ($currentUser instanceof User && $currentUser->getCompany())
            ? $currentUser->getCompany()->getName()
            : 'Phoenix Athletics';

        $companyName = $data['companyName'] ?? $defaultCompanyName;
        $company = $this->entityManager->getRepository(\App\Entity\Company::class)->findOneBy(['name' => $companyName]);

        $isNewCompany = false;
        if (!$company) {
            $company = new \App\Entity\Company();
            $company->setName($companyName);

            $this->entityManager->persist($company);
            $isNewCompany = true;
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setCompany($company);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Handle multiple roles
        if ($isAdminCreation) {
            $roles = ['ROLE_MEMBER']; // Default
            if (isset($data['roles']) && is_array($data['roles'])) {
                $roles = $data['roles'];
            } elseif (isset($data['role'])) {
                $roles = [$data['role']];
            }

            $allowedRoles = ['ROLE_MEMBER', 'ROLE_TRAINER', 'ROLE_ADMIN', 'ROLE_TRIAL'];
            $finalRoles = array_intersect($roles, $allowedRoles);
            if (empty($finalRoles)) {
                $finalRoles = ['ROLE_MEMBER'];
            }
        } else {
            // Public registration logic
            if ($isNewCompany) {
                $finalRoles = ['ROLE_ADMIN'];
            } else {
                $finalRoles = ['ROLE_TRIAL'];
            }
        }

        $user->setRoles(array_values($finalRoles));

        if ($isAdminCreation) {
            $user->setIsVerified(true);
            $user->setIsActive(true);
            $user->setMustChangePassword(true);
        } else {
            $user->setIsVerified(false);
            $user->setIsActive(false);
            $user->setMustChangePassword(false);
        }

        $this->generateVerificationToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->welcomeEmailService->sendWelcomeEmail($user, $isNewCompany, $password, $isAdminCreation);

        if (!$isAdminCreation && !$isNewCompany) {
            $this->sendAdminNotificationEmail($user);
        }

        return $user;
    }

    private function sendAdminNotificationEmail(User $user): void
    {
        $admins = $this->entityManager->getRepository(User::class)->findByRole('ROLE_ADMIN');
        $adminEmails = array_map(fn(User $admin) => $admin->getEmail(), $admins);

        if (empty($adminEmails)) {
            // Fallback to a configured admin email if no admin users found in DB
            $fallbackAdmin = $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com';
            $adminEmails = [$fallbackAdmin];
        }

        $email = (new TemplatedEmail())
            ->from($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com')
            ->to(...$adminEmails)
            ->subject('New User Registration: ' . $user->getName())
            ->htmlTemplate('emails/admin_new_user.html.twig')
            ->context([
                'name' => $user->getName(),
                'userEmail' => $user->getEmail(),
                'role' => implode(', ', array_map(fn($r) => str_replace('ROLE_', '', $r), $user->getRoles())),
            ]);

        $this->mailer->send($email);
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

        $this->welcomeEmailService->sendWelcomeEmail($user);
    }

    private function generateVerificationToken(User $user): void
    {
        $user->setVerificationToken(Uuid::v4()->toBase58());
        $user->setVerificationTokenExpiresAt(new \DateTime('+24 hours'));
    }
}
