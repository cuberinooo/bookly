<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'notification_index', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository, SerializerInterface $serializer): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $notifications = $notificationRepository->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']);
        $json = $serializer->serialize($notifications, 'json', ['groups' => 'notification:read']);
        
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/read', name: 'notification_read', methods: ['PATCH'])]
    public function read($id, NotificationRepository $notificationRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $notification = $notificationRepository->find($id);
        
        if (!$notification || $notification->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $notification->setIsRead(true);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Notification marked as read']);
    }
}
