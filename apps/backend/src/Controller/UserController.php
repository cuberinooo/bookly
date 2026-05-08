<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

use App\Service\PasswordValidator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\AdminUserService;
use Aws\S3\S3ClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/user')]
class UserController extends AbstractController
{
    public function __construct(
        private S3ClientInterface $s3Client,
        private string $s3Bucket,
        private SluggerInterface $slugger,
    ) {}

    #[Route('/me', name: 'user_delete', methods: ['DELETE'])]
    public function deleteMe(AdminUserService $adminUserService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $adminUserService->deleteUser($user);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['status' => 'Account deleted successfully']);
    }

    #[Route('/profile-picture', name: 'user_profile_picture_upload', methods: ['POST'])]
    public function uploadProfilePicture(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $company = $user->getCompany();
        if (!$company) {
            return new JsonResponse(['error' => 'User has no company'], Response::HTTP_BAD_REQUEST);
        }

        $companySlug = $this->slugger->slug($company->getName())->lower();
        $extension = $file->guessExtension() ?? 'jpg';
        $filename = sprintf('profile_%s.%s', uniqid('', true), $extension);
        $key = $companySlug . '/' . $user->getId() . '/' . $filename;

        try {
            $prefix = $companySlug . '/' . $user->getId() . '/';
            $this->s3Client->deleteMatchingObjects($this->s3Bucket, $prefix);

            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
                'Body'   => fopen($file->getRealPath(), 'r'),
            ]);

            $user->setProfilePicture($filename);
            $entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to save file to S3: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Profile picture updated', 'profilePicture' => $filename]);
    }

    #[Route('/profile-picture/{id}', name: 'user_profile_picture_serve', methods: ['GET'])]
    public function serveProfilePicture(int $id, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($id);
        if (!$user || !$user->getProfilePicture()) {
            throw $this->createNotFoundException('Profile picture not found');
        }

        $company = $user->getCompany();
        if (!$company) {
             throw $this->createNotFoundException('Company not found');
        }

        $companySlug = $this->slugger->slug($company->getName())->lower();
        $key = $companySlug . '/' . $user->getId() . '/' . $user->getProfilePicture();

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $key,
            ]);
        } catch (\Aws\S3\Exception\S3Exception $e) {
            // If the file doesn't exist in the bucket, gracefully throw a 404
            throw $this->createNotFoundException('Profile picture not found in storage.', $e);
        }

        $content = $result['Body']->getContents();
        $contentType = $result['ContentType'] ?? 'application/octet-stream';

        return new Response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . basename($key) . '"',
        ]);
    }

    #[Route('/me', name: 'user_me', methods: ['GET'])]
    public function me(SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        $json = $serializer->serialize($user, 'json', ['groups' => 'user:read']);
        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/change-password', name: 'user_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        PasswordValidator $passwordValidator,
        UserRepository $userRepository,
        \Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $userInterface = $this->getUser();
        if (!$userInterface) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $userRepository->findOneBy(['email' => $userInterface->getUserIdentifier()]);
        if (!$user) {
            return new JsonResponse(['error' => 'User entity not found'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $newPassword = $data['password'] ?? null;

        if (!$newPassword) {
            return new JsonResponse(['error' => 'New password required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $passwordValidator->validate($newPassword);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setMustChangePassword(false);

        $entityManager->flush();

        $token = $jwtManager->create($user);

        return new JsonResponse([
            'status' => 'Password changed successfully',
            'token' => $token
        ]);
    }

    #[Route('/me', name: 'user_update', methods: ['PATCH'])]
    public function update(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['courseStartNotificationHours']) || isset($data['courseStartNotificationMinutes'])) {
            $hours = (int) ($data['courseStartNotificationHours'] ?? $user->getCourseStartNotificationHours());
            $minutes = (int) ($data['courseStartNotificationMinutes'] ?? $user->getCourseStartNotificationMinutes());

            $totalMinutes = ($hours * 60) + $minutes;

            if ($totalMinutes !== 0) {
                if ($totalMinutes < 5) {
                    return new JsonResponse(['error' => 'Notification must be at least 5 minutes.'], 400);
                }
                if ($totalMinutes % 5 !== 0) {
                    return new JsonResponse(['error' => 'Notification must be in 5-minute increments.'], 400);
                }
            }

            $user->setCourseStartNotificationHours($hours);
            $user->setCourseStartNotificationMinutes($minutes);
        }

        if (isset($data['roles']) && is_array($data['roles']) && $this->isGranted('ROLE_ADMIN')) {
            $allowedRoles = ['ROLE_MEMBER', 'ROLE_TRAINER', 'ROLE_ADMIN', 'ROLE_TRIAL'];
            $newRoles = array_intersect($data['roles'], $allowedRoles);
            if (!empty($newRoles)) {
                $user->setRoles(array_values($newRoles));
            }
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'Profile updated']);
    }

    #[Route('/trainers', name: 'user_trainers', methods: ['GET'])]
    public function trainers(UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        $trainers = $userRepository->findByRole('ROLE_TRAINER');
        $json = $serializer->serialize($trainers, 'json', ['groups' => 'user:read']);
        return new JsonResponse($json, 200, [], true);
    }
}
