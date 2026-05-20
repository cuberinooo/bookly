<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Booking;
use App\Entity\SensitiveDataAccessLog;
use App\Repository\UserRepository;
use App\Service\AdminUserService;
use App\Service\UserService;
use Aws\S3\S3ClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/user')]
class UserController extends AbstractController
{
    public function __construct(
        private S3ClientInterface $s3Client,
        private string $s3Bucket,
        private SluggerInterface $slugger,
        private UserService $userService,
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
    public function uploadProfilePicture(Request $request): JsonResponse
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

        try {
            $filename = $this->userService->uploadProfilePicture($user, $file);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
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

    #[Route('/{id}/emergency-contact', name: 'user_emergency_contact', methods: ['GET'])]
    public function getEmergencyContact(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $trainer */
        $trainer = $this->getUser();
        $targetUser = $userRepository->find($id);

        if (!$targetUser) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_TRAINER')) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $isAuthorized = $this->isGranted('ROLE_ADMIN');

        if (!$isAuthorized) {
            $now = new \DateTime();
            $startTime = (clone $now)->modify('-2 hours');
            $endTime = (clone $now)->modify('+2 hours');

            $bookings = $entityManager->getRepository(Booking::class)->createQueryBuilder('b')
                ->join('b.course', 'c')
                ->where('b.user = :user')
                ->andWhere('c.user = :trainer')
                ->andWhere('c.startTime <= :endTime')
                ->andWhere('c.endTime >= :startTime')
                ->setParameter('user', $targetUser)
                ->setParameter('trainer', $trainer)
                ->setParameter('startTime', $startTime)
                ->setParameter('endTime', $endTime)
                ->getQuery()
                ->getResult();

            if (!empty($bookings)) {
                $isAuthorized = true;
            }
        }

        if (!$isAuthorized) {
            return new JsonResponse(['error' => 'You can only access emergency info for active session participants.'], Response::HTTP_FORBIDDEN);
        }

        $log = new SensitiveDataAccessLog();
        $log->setViewer($trainer);
        $log->setTargetUser($targetUser);
        $log->setReason('Emergency contact access via Trainer Dashboard');
        
        $entityManager->persist($log);
        $entityManager->flush();

        return new JsonResponse([
            'phoneNumber' => $targetUser->getPhoneNumber(),
            'emergencyContactName' => $targetUser->getEmergencyContactName(),
            'emergencyContactPhone' => $targetUser->getEmergencyContactPhone()
        ]);
    }

    #[Route('/change-password', name: 'user_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
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
            $this->userService->changePassword($user, $newPassword);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $token = $jwtManager->create($user);

        return new JsonResponse([
            'status' => 'Password changed successfully',
            'token' => $token
        ]);
    }

    #[Route('/me', name: 'user_update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $this->userService->updateProfile($user, $data, $this->isGranted('ROLE_ADMIN'));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['status' => 'Profile updated']);
    }

    #[Route('/trainers', name: 'user_trainers', methods: ['GET'])]
    public function trainers(UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        $trainers = $userRepository->findByRole('ROLE_TRAINER');
        $json = $serializer->serialize($trainers, 'json', ['groups' => 'user:read']);
        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/profile-pictures', name: 'user_profile_pictures_batch', methods: ['GET'])]
    public function batchProfilePictures(Request $request, UserRepository $userRepository): JsonResponse
    {
        $ids = $request->query->get('ids');
        if (!$ids) {
            return new JsonResponse([]);
        }

        $idArray = array_map('intval', explode(',', $ids));
        $users = $userRepository->findBy(['id' => $idArray]);

        $data = [];
        foreach ($users as $user) {
            if ($user->getProfilePicture()) {
                $data[$user->getId()] = $user->getProfilePicture();
            }
        }

        return new JsonResponse($data);
    }
}
