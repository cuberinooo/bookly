<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\EmailAlreadyRegisteredException;
use App\Repository\AdminSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private PasswordValidator $passwordValidator,
        private Security $security,
        private EmailService $emailService,
        private TranslatorInterface $translator
    ) {
    }

    public function register(array $data, bool $isAdminCreation = false): User
    {
        $filters = $this->entityManager->getFilters();
        $companyFilterEnabled = $filters->isEnabled('company_filter');
        if ($companyFilterEnabled) {
            $filters->disable('company_filter');
        }

        try {
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        } finally {
            if ($companyFilterEnabled) {
                $filters->enable('company_filter');
            }
        }

        if ($existingUser) {
            throw new EmailAlreadyRegisteredException($this->translator->trans('error.email_already_registered'));
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

        if (isset($data['gender'])) {
            $user->setGender(\App\Enum\Gender::tryFrom($data['gender']));
        }

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
            $user->setIsActive(true);
            $user->setMustChangePassword(false);
        }

        $this->generateVerificationToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->emailService->sendVerificationEmail($user, $isAdminCreation, $password);

        if (!$isNewCompany) {
            $this->emailService->sendCompanySpecificWelcomeEmail($user);

            if (!$isAdminCreation) {
                $admins = $this->entityManager->getRepository(User::class)->findByRole('ROLE_ADMIN', $user->getCompany());
                $this->emailService->sendAdminNotificationEmail($user, $admins);
            }
        }

        return $user;
    }

    public function verifyEmail(string $token): void
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            throw new \Exception($this->translator->trans('error.invalid_token'));
        }

        if ($user->getVerificationTokenExpiresAt() < new \DateTime()) {
            throw new \Exception($this->translator->trans('error.token_expired'));
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

        $this->emailService->sendVerificationEmail($user);
    }

    private function generateVerificationToken(User $user): void
    {
        $user->setVerificationToken(Uuid::v4()->toBase58());
        $user->setVerificationTokenExpiresAt(new \DateTime('+24 hours'));
    }
}
