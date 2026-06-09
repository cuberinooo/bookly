<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/push-subscriptions')]
class PushSubscriptionController extends AbstractController
{
    #[Route('', name: 'push_subscriptions_create', methods: ['POST'])]
    public function create(
        Request $request,
        PushSubscriptionRepository $repository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['endpoint']) || !isset($data['keys']['p256dh']) || !isset($data['keys']['auth'])) {
            return new JsonResponse(['error' => 'Invalid push subscription payload'], Response::HTTP_BAD_REQUEST);
        }

        $endpoint = $data['endpoint'];
        $p256dh = $data['keys']['p256dh'];
        $auth = $data['keys']['auth'];

        // Avoid duplicates by searching for existing subscription with this endpoint
        $subscription = $repository->findOneBy(['endpoint' => $endpoint]);

        if ($subscription) {
            // If it exists but belongs to a different user, reassign it
            $subscription->setUser($user);
            $subscription->setCompany($user->getCompany());
            $subscription->setP256dh($p256dh);
            $subscription->setAuth($auth);
        } else {
            $subscription = new PushSubscription();
            $subscription->setUser($user);
            $subscription->setCompany($user->getCompany());
            $subscription->setEndpoint($endpoint);
            $subscription->setP256dh($p256dh);
            $subscription->setAuth($auth);
            $entityManager->persist($subscription);
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'Subscription saved successfully'], Response::HTTP_OK);
    }

    #[Route('/unsubscribe', name: 'push_subscriptions_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        PushSubscriptionRepository $repository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['endpoint'])) {
            return new JsonResponse(['error' => 'Endpoint required'], Response::HTTP_BAD_REQUEST);
        }

        $subscription = $repository->findOneBy(['endpoint' => $data['endpoint'], 'user' => $user]);
        if ($subscription) {
            $entityManager->remove($subscription);
            $entityManager->flush();
        }

        return new JsonResponse(['status' => 'Unsubscribed successfully'], Response::HTTP_OK);
    }
}
