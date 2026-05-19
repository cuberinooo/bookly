<?php

namespace App\Controller;

use App\Entity\CycleAssignment;
use App\Entity\TrainingCategory;
use App\Entity\TrainingCycle;
use App\Repository\TrainingCategoryRepository;
use App\Repository\TrainingCycleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/training-cycles')]
class TrainingCycleController extends AbstractController
{
    #[Route('/categories', name: 'training_category_index', methods: ['GET'])]
    public function indexCategories(TrainingCategoryRepository $repository, SerializerInterface $serializer): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $categories = $repository->findBy(['trainer' => $this->getUser()]);
        $json = $serializer->serialize($categories, 'json', ['groups' => 'category:read']);
        return new JsonResponse(json_decode($json, true), Response::HTTP_OK);
    }

    #[Route('/categories', name: 'training_category_new', methods: ['POST'])]
    public function newCategory(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $data = json_decode($request->getContent(), true);

        $category = new TrainingCategory();
        $category->setTrainer($this->getUser());
        $category->setCompany($this->getUser()->getCompany());
        $category->setName($data['name']);
        $category->setColorHex($data['colorHex']);

        $entityManager->persist($category);
        $entityManager->flush();

        return new JsonResponse(['id' => $category->getId()], Response::HTTP_CREATED);
    }

    #[Route('/categories/{id}', name: 'training_category_delete', methods: ['DELETE'])]
    public function deleteCategory(TrainingCategory $category, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        if ($category->getTrainer() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($category);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('', name: 'training_cycle_index', methods: ['GET'])]
    public function indexCycles(TrainingCycleRepository $repository, SerializerInterface $serializer): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $cycles = $repository->findBy(['trainer' => $this->getUser()], ['startDate' => 'DESC']);
        $json = $serializer->serialize($cycles, 'json', ['groups' => 'cycle:read']);
        return new JsonResponse(json_decode($json, true), Response::HTTP_OK);
    }

    #[Route('', name: 'training_cycle_new', methods: ['POST'])]
    public function saveCycle(Request $request, EntityManagerInterface $entityManager, TrainingCategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $data = json_decode($request->getContent(), true);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Get or create the single cycle for this trainer
        $cycle = $entityManager->getRepository(TrainingCycle::class)->findOneBy(['trainer' => $user]);
        if (!$cycle) {
            $cycle = new TrainingCycle();
            $cycle->setTrainer($user);
            $cycle->setCompany($user->getCompany());
        } else {
            // Clear existing assignments for replacement logic
            foreach ($cycle->getAssignments() as $assignment) {
                $entityManager->remove($assignment);
            }
            $cycle->getAssignments()->clear();
        }

        $cycle->setName($data['name'] ?? 'Training Cycle');
        $cycle->setStartDate(new \DateTime($data['startDate']));
        $cycle->setDurationWeeks($data['durationWeeks'] ?? 4);
        $cycle->setIsActive($data['isActive'] ?? true);

        foreach ($data['assignments'] ?? [] as $assignData) {
            $category = $categoryRepository->find($assignData['categoryId']);
            if ($category && $category->getTrainer() === $user) {
                $assignment = new CycleAssignment();
                $assignment->setWeekNumber($assignData['weekNumber']);
                $assignment->setDayOfWeek($assignData['dayOfWeek']);
                $assignment->setCategory($category);
                $cycle->addAssignment($assignment);
            }
        }

        $entityManager->persist($cycle);
        $entityManager->flush();

        return new JsonResponse(['id' => $cycle->getId()], Response::HTTP_OK);
    }

    #[Route('/status', name: 'training_cycle_toggle', methods: ['PATCH'])]
    public function toggleStatus(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $data = json_decode($request->getContent(), true);
        
        $cycle = $entityManager->getRepository(TrainingCycle::class)->findOneBy(['trainer' => $this->getUser()]);
        if (!$cycle) {
            return new JsonResponse(['error' => 'No cycle found'], Response::HTTP_NOT_FOUND);
        }

        $cycle->setIsActive($data['isActive']);
        $entityManager->flush();

        return new JsonResponse(['status' => $cycle->isIsActive() ? 'Activated' : 'Deactivated']);
    }

    #[Route('/{id}', name: 'training_cycle_delete', methods: ['DELETE'])]
    public function deleteCycle(TrainingCycle $cycle, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        if ($cycle->getTrainer() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($cycle);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
