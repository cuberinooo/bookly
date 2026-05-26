<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exercise;
use App\Service\LeaderboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/leaderboard')]
#[IsGranted('ROLE_USER')]
class LeaderboardController extends AbstractController
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService
    ) {
    }

    #[Route('/monthly-stats', name: 'api_leaderboard_monthly_stats', methods: ['GET'])]
    public function getMonthlyStats(): JsonResponse
    {
        return $this->json($this->leaderboardService->getMonthlyStats());
    }

    #[Route('/exercises', name: 'api_leaderboard_exercises', methods: ['GET'])]
    public function getExercises(EntityManagerInterface $em): JsonResponse
    {
        $exercises = $em->getRepository(Exercise::class)->findBy([], ['category' => 'ASC', 'name' => 'ASC']);

        return $this->json($exercises, context: ['groups' => ['exercise:read']]);
    }

    #[Route('/workout-records', name: 'api_leaderboard_post_record', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function postRecord(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['exerciseName']) || !isset($data['weightValue'])) {
            return $this->json(['error' => 'Missing fields'], 400);
        }

        try {
            $record = $this->leaderboardService->submitRecord(
                $user,
                $data['exerciseName'],
                (float) $data['weightValue']
            );

            return $this->json(['status' => 'success', 'id' => $record->getId()], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/workout-records', name: 'api_leaderboard_get_records', methods: ['GET'])]
    public function getRecords(): JsonResponse
    {
        return $this->json($this->leaderboardService->getWorkoutRecords());
    }
}
