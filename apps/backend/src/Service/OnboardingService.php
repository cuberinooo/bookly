<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class OnboardingService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Marks a specific task as complete for the user.
     */
    public function markTaskComplete(User $user, string $taskId): void
    {
        $state = $user->getOnboardingState();

        if (!in_array($taskId, $state, true)) {
            $state[] = $taskId;
            $user->setOnboardingState($state);
            $this->entityManager->flush();
        }
    }

    /**
     * Skips the entire onboarding process.
     */
    public function skipOnboarding(User $user): void
    {
        $user->setOnboardingState(['skipped']);
        $this->entityManager->flush();
    }
}
