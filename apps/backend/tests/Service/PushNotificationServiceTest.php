<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PushNotificationServiceTest extends TestCase
{
    private $entityManager;
    private $repository;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(PushSubscriptionRepository::class);

        $this->service = new PushNotificationService(
            $this->entityManager,
            $this->repository,
            'BJLIKhkOzKs3eY2S1ToAkDbG4BOWsDvpI_RdoOQlmoN4AEBJq4VspsOwP6NLNfNYjS2IGU_25rPxli6hn63vUO8',
            'V_iH-Eot93xgON0LCQ_pFoqWeHiALCcoZMjs7dAhW7c',
            'mailto:admin@booklyfit.local'
        );
    }

    protected function tearDown(): void
    {
        $this->entityManager = null;
        $this->repository = null;
        $this->service = null;
        parent::tearDown();
    }

    public function test_send_notification_with_no_subscriptions_returns_early(): void
    {
        $user = $this->createMock(User::class);

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['user' => [$user]])
            ->willReturn([]);

        // This should run without throwing errors or making external HTTP requests
        $this->service->sendNotification($user, 'Hello!', 'Test message');
        $this->assertTrue(true);
    }
}
