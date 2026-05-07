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
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/api/user')]
class UserController extends AbstractController
{
    private string $uploadDir;

    public function __construct(ParameterBagInterface $params)
    {
        $this->uploadDir = $params->get('upload_dir');
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

        $companyDir = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $company->getName());
        $targetDir = $this->uploadDir . '/' . $companyDir . '/' . $user->getId();

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $extension = $file->guessExtension() ?? 'jpg';
        $filename = 'profile.' . $extension;

        try {
            $file->move($targetDir, $filename);
            $user->setProfilePicture($filename);
            $entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to save file: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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

        $companyDir = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $company->getName());
        $fullPath = $this->uploadDir . '/' . $companyDir . '/' . $user->getId() . '/' . $user->getProfilePicture();

        if (!file_exists($fullPath)) {
            throw $this->createNotFoundException('File not found');
        }

        return $this->file($fullPath);
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
