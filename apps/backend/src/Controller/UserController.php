<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/user')]
class UserController extends AbstractController
{
    #[Route('/me', name: 'user_me', methods: ['GET'])]
    public function me(SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        $json = $serializer->serialize($user, 'json', ['groups' => 'user:read']);
        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/me', name: 'user_update', methods: ['PATCH'])]
    public function update(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'Profile updated']);
    }
}
