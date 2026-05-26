<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Aws\S3\S3ClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class AdminUserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private S3ClientInterface $s3Client,
        private SluggerInterface $slugger,
        private string $s3Bucket
    ) {
    }

    /**
     * @param bool $deactivateIfHasCourses If true, deactivate the user if they have courses. If false, throw an exception.
     *
     * @throws \Exception If deletion is not possible (and $deactivateIfHasCourses is false)
     *
     * @return bool true if deleted, false if deactivated
     */
    public function deleteUser(User $user, bool $deactivateIfHasCourses = false): bool
    {
        if (!$user->getCourses()->isEmpty()) {
            if ($deactivateIfHasCourses) {
                $user->setIsActive(false);
                $this->entityManager->flush();

                return false;
            }
            throw new \Exception('Cannot delete account. You still have active courses. Please transfer your courses to another trainer first.');
        }

        // Cleanup profile picture directory in S3
        $company = $user->getCompany();
        if ($company) {
            $companySlug = $this->slugger->slug($company->getName())->lower();
            $prefix = $companySlug.'/'.$user->getId().'/';

            try {
                $this->s3Client->deleteMatchingObjects($this->s3Bucket, $prefix);
            } catch (\Exception $e) {
                // Log error but continue
            }
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return true;
    }

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
                'siteUrl' => $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200',
            ]);

        $this->mailer->send($email);
    }
}
