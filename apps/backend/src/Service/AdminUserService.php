<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Aws\S3\S3ClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class AdminUserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailService $emailService,
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

        // Clean up sensitive data access logs where this user is viewer or target user
        $this->entityManager->createQueryBuilder()
            ->delete(\App\Entity\SensitiveDataAccessLog::class, 'log')
            ->where('log.viewer = :user')
            ->orWhere('log.targetUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

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

        $this->emailService->sendPasswordResetEmail($user, $temporaryPassword);

        return $temporaryPassword;
    }

    private function generateRandomPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';

        return substr(str_shuffle($chars), 0, $length);
    }
}
