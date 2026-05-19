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
    public function newCycle(Request $request, EntityManagerInterface $entityManager, TrainingCategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $data = json_decode($request->getContent(), true);

        // Deactivate other cycles for this trainer
        $existingCycles = $entityManager->getRepository(TrainingCycle::class)->findBy(['trainer' => $this->getUser(), 'isActive' => true]);
        foreach ($existingCycles as $existingCycle) {
            $existingCycle->setIsActive(false);
        }

        $cycle = new TrainingCycle();
        $cycle->setTrainer($this->getUser());
        $cycle->setCompany($this->getUser()->getCompany());
        $cycle->setName($data['name']);
        $cycle->setStartDate(new \DateTime($data['startDate']));
        $cycle->setDurationWeeks($data['durationWeeks'] ?? 4);
        $cycle->setIsActive(true);

        foreach ($data['assignments'] ?? [] as $assignData) {
            $category = $categoryRepository->find($assignData['categoryId']);
            if ($category && $category->getTrainer() === $this->getUser()) {
                $assignment = new CycleAssignment();
                $assignment->setWeekNumber($assignData['weekNumber']);
                $assignment->setDayOfWeek($assignData['dayOfWeek']);
                $assignment->setCategory($category);
                $cycle->addAssignment($assignment);
            }
        }

        $entityManager->persist($cycle);
        $entityManager->flush();

        return new JsonResponse(['id' => $cycle->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}/activate', name: 'training_cycle_activate', methods: ['PATCH'])]
    public function activateCycle(TrainingCycle $cycle, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        if ($cycle->getTrainer() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Deactivate other cycles
        $existingCycles = $entityManager->getRepository(TrainingCycle::class)->findBy(['trainer' => $this->getUser(), 'isActive' => true]);
        foreach ($existingCycles as $existingCycle) {
            $existingCycle->setIsActive(false);
        }

        $cycle->setIsActive(true);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Activated']);
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
