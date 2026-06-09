<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PushNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushSubscriptionRepository $repository,
        #[Autowire(env: 'VAPID_PUBLIC_KEY')] private readonly string $vapidPublicKey,
        #[Autowire(env: 'VAPID_PRIVATE_KEY')] private readonly string $vapidPrivateKey,
        #[Autowire(env: 'VAPID_SUBJECT')] private readonly string $vapidSubject
    ) {
    }

    /**
     * Sends a push notification to a single user.
     */
    public function sendNotification(User $user, string $title, string $message, ?string $actionUrl = null): void
    {
        $this->sendNotificationToUsers([$user], $title, $message, $actionUrl);
    }

    /**
     * Sends a push notification to multiple users.
     *
     * @param User[] $users
     */
    public function sendNotificationToUsers(array $users, string $title, string $message, ?string $actionUrl = null): void
    {
        if (empty($users)) {
            return;
        }

        $subscriptions = $this->repository->findBy(['user' => $users]);
        if (empty($subscriptions)) {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ];

        $webPush = new WebPush($auth);

        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'url' => $actionUrl,
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->getEndpoint(),
                    'keys' => [
                        'p256dh' => $sub->getP256dh(),
                        'auth' => $sub->getAuth(),
                    ],
                ]),
                $payload
            );
        }

        $failedEndpoints = [];

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $endpoint = $report->getEndpoint();
                $statusCode = $report->getResponse()?->getStatusCode();

                if ($report->isSubscriptionExpired() || 404 === $statusCode || 410 === $statusCode) {
                    $failedEndpoints[] = $endpoint;
                }
            }
        }

        if (!empty($failedEndpoints)) {
            // Bulk delete failed subscriptions
            $qb = $this->entityManager->createQueryBuilder();
            $qb->delete(\App\Entity\PushSubscription::class, 's')
                ->where('s.endpoint IN (:endpoints)')
                ->setParameter('endpoints', $failedEndpoints)
                ->getQuery()
                ->execute();
        }
    }
}
