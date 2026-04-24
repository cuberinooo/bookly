<?php

namespace App\Controller;

use App\Entity\Course;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;
use App\Service\CourseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/courses')]
class CourseController extends AbstractController
{
    #[Route('', name: 'course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository, SerializerInterface $serializer): JsonResponse
    {
        $courses = $courseRepository->findAll();
        $json = $serializer->serialize($courses, 'json', ['groups' => 'course:read']);
        
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'course_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CourseService $courseService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        $data = json_decode($request->getContent(), true);
        
        $startTime = new \DateTime($data['startTime']);
        $duration = (int) ($data['durationMinutes'] ?? 60);
        $endTime = clone $startTime;
        $endTime->modify("+$duration minutes");

        try {
            $courseService->validateSchedule($startTime, $endTime);
        } catch (ScheduleConflictException $e) {
            return new JsonResponse(['error' => $e->getFrontendMessage()], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $course = new Course();
        $course->setTitle($data['title']);
        $course->setDescription($data['description'] ?? '');
        $course->setCapacity((int) $data['capacity']);
        $course->setStartTime($startTime);
        $course->setDurationMinutes($duration);
        $course->setEndTime($endTime);
        $course->setTrainer($this->getUser());

        $entityManager->persist($course);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Course created', 'id' => $course->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'course_show', methods: ['GET'])]
    public function show(Course $course, SerializerInterface $serializer): JsonResponse
    {
        $json = $serializer->serialize($course, 'json', ['groups' => 'course:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'course_edit', methods: ['PATCH'])]
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager, CourseService $courseService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        
        if ($course->getTrainer() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['startTime']) || isset($data['durationMinutes'])) {
            $startTime = isset($data['startTime']) ? new \DateTime($data['startTime']) : $course->getStartTime();
            $duration = isset($data['durationMinutes']) ? (int) $data['durationMinutes'] : $course->getDurationMinutes();
            
            $endTime = clone $startTime;
            $endTime->modify("+$duration minutes");

            try {
                $courseService->validateSchedule($startTime, $endTime, $course->getId());
            } catch (ScheduleConflictException $e) {
                return new JsonResponse(['error' => $e->getFrontendMessage()], Response::HTTP_CONFLICT);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $course->setStartTime($startTime);
            $course->setDurationMinutes($duration);
            $course->setEndTime($endTime);
        }

        if (isset($data['title'])) $course->setTitle($data['title']);
        if (isset($data['description'])) $course->setDescription($data['description']);
        if (isset($data['capacity'])) $course->setCapacity((int) $data['capacity']);

        $entityManager->flush();

        return new JsonResponse(['status' => 'Course updated']);
    }

    #[Route('/{id}', name: 'course_delete', methods: ['DELETE'])]
    public function delete(Course $course, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        
        if ($course->getTrainer() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($course);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Course deleted']);
    }
}
