<?php

namespace App\Controller;

use App\Enum\BookingWindow;
use App\Repository\GlobalSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/settings')]
class GlobalSettingsController extends AbstractController
{
    #[Route('', name: 'settings_get', methods: ['GET'])]
    public function getSettings(SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !$user->getCompany()) {
             return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $settings = $user->getCompany()->getGlobalSettings();
        $json = $serializer->serialize($settings, 'json', ['groups' => 'settings:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'settings_update', methods: ['PATCH'])]
    public function updateSettings(
        Request $request, 
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || !$user->getCompany()) {
             return new JsonResponse(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $settings = $user->getCompany()->getGlobalSettings();
        $data = json_decode($request->getContent(), true);

        if (isset($data['showParticipantNames'])) {
            $settings->setShowParticipantNames((bool) $data['showParticipantNames']);
        }
        if (isset($data['isWaitlistVisible'])) {
            $settings->setWaitlistVisible((bool) $data['isWaitlistVisible']);
        }
        if (isset($data['bookingWindow'])) {
            $window = BookingWindow::tryFrom($data['bookingWindow']);
            if ($window) {
                $settings->setBookingWindow($window);
            }
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'Global settings updated']);
    }
}
