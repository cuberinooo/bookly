<?php

namespace App\Service;

use App\Entity\User;
use App\Service\PasswordValidator;
use Aws\S3\S3ClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private PasswordValidator $passwordValidator,
        private S3ClientInterface $s3Client,
        private string $s3Bucket,
        private SluggerInterface $slugger,
    ) {}

    /**
     * Uploads a user's profile picture to S3 and updates the User entity.
     */
    public function uploadProfilePicture(User $user, UploadedFile $file): string
    {
        $company = $user->getCompany();
        if (!$company) {
            throw new \InvalidArgumentException('User has no company');
        }

        $companySlug = $this->slugger->slug($company->getName())->lower();
        $extension = $file->guessExtension() ?? 'jpg';
        $filename = sprintf('profile_%s.%s', uniqid('', true), $extension);
        $key = $companySlug . '/' . $user->getId() . '/' . $filename;

        // Clean up existing profile pictures
        $prefix = $companySlug . '/' . $user->getId() . '/';
        $this->s3Client->deleteMatchingObjects($this->s3Bucket, $prefix);

        // Upload new file
        $this->s3Client->putObject([
            'Bucket' => $this->s3Bucket,
            'Key'    => $key,
            'Body'   => fopen($file->getRealPath(), 'r'),
        ]);

        $user->setProfilePicture($filename);
        $this->entityManager->flush();

        return $filename;
    }

    /**
     * Updates the user's profile information.
     */
    public function updateProfile(User $user, array $data, bool $isAdmin = false): void
    {
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['phoneNumber'])) {
            $user->setPhoneNumber($data['phoneNumber']);
        }

        if (isset($data['emergencyContactName'])) {
            $user->setEmergencyContactName($data['emergencyContactName']);
        }

        if (isset($data['emergencyContactPhone'])) {
            $user->setEmergencyContactPhone($data['emergencyContactPhone']);
        }

        if (isset($data['gender'])) {
            $user->setGender(\App\Enum\Gender::tryFrom($data['gender']));
        }

        if (isset($data['isPublic'])) {
            $user->setIsPublic((bool) $data['isPublic']);
        }

        if (isset($data['courseStartNotificationHours']) || isset($data['courseStartNotificationMinutes'])) {
            $hours = (int) ($data['courseStartNotificationHours'] ?? $user->getCourseStartNotificationHours());
            $minutes = (int) ($data['courseStartNotificationMinutes'] ?? $user->getCourseStartNotificationMinutes());

            $totalMinutes = ($hours * 60) + $minutes;

            if ($totalMinutes !== 0) {
                if ($totalMinutes < 5) {
                    throw new \InvalidArgumentException('Notification must be at least 5 minutes.');
                }
                if ($totalMinutes % 5 !== 0) {
                    throw new \InvalidArgumentException('Notification must be in 5-minute increments.');
                }
            }

            $user->setCourseStartNotificationHours($hours);
            $user->setCourseStartNotificationMinutes($minutes);
        }

        if ($isAdmin && isset($data['roles']) && is_array($data['roles'])) {
            $allowedRoles = ['ROLE_MEMBER', 'ROLE_TRAINER', 'ROLE_ADMIN', 'ROLE_TRIAL'];
            $newRoles = array_intersect($data['roles'], $allowedRoles);
            if (!empty($newRoles)) {
                $user->setRoles(array_values($newRoles));
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Validates and changes the user's password.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $this->passwordValidator->validate($newPassword);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setMustChangePassword(false);

        $this->entityManager->flush();
    }
}
