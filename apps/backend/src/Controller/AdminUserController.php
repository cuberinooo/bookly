<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository, SerializerInterface $serializer): JsonResponse
    {
        $users = $userRepository->findAll();
        // Filter out admins from the list if desired, or keep them. 
        // User asked to manage non-admin users.
        $filteredUsers = array_filter($users, function(User $user) {
            return !in_array('ROLE_ADMIN', $user->getRoles());
        });

        $json = $serializer->serialize(array_values($filteredUsers), 'json', ['groups' => 'user:read']);
        return new JsonResponse($json, 200, [], true);
    }

    #[Route('', name: 'admin_user_create', methods: ['POST'])]
    public function create(Request $request, RegistrationService $registrationService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['name'])) {
            return new JsonResponse(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $registrationService->register($data, true);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['status' => 'User created successfully. Temporary password sent via email.'], Response::HTTP_CREATED);
    }

    #[Route('/{id}/toggle-active', name: 'admin_user_toggle_active', methods: ['PATCH'])]
    public function toggleActive(User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Cannot deactivate admin accounts'], Response::HTTP_FORBIDDEN);
        }

        $user->setIsActive(!$user->isActive());
        $entityManager->flush();

        return new JsonResponse(['status' => 'User status updated', 'isActive' => $user->isActive()]);
    }

    #[Route('/{id}', name: 'admin_user_delete', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $entityManager, \App\Repository\CourseRepository $courseRepository): JsonResponse
    {
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Cannot delete admin accounts'], Response::HTTP_FORBIDDEN);
        }

        // If trainer has courses, deactivate instead of delete
        $isTrainer = in_array('ROLE_TRAINER', $user->getRoles());
        if ($isTrainer) {
            $courses = $courseRepository->findBy(['trainer' => $user]);
            if (count($courses) > 0) {
                $user->setIsActive(false);
                $entityManager->flush();
                return new JsonResponse(['status' => 'Trainer has existing courses. Account deactivated instead of deleted.']);
            }
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(['status' => 'User deleted successfully']);
    }
    
    #[Route('/{id}', name: 'admin_user_update', methods: ['PATCH'])]
    public function update(User $user, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Cannot edit admin accounts via this endpoint'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) $user->setName($data['name']);
        if (isset($data['role'])) {
            $role = $data['role'];
            if (in_array($role, ['ROLE_MEMBER', 'ROLE_TRAINER'])) {
                $user->setRoles([$role]);
            }
        }
        
        $entityManager->flush();
        return new JsonResponse(['status' => 'User updated']);
    }
}
