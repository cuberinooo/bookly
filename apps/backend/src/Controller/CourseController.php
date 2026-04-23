<?php

namespace App\Controller;

use App\Entity\Course;
use App\Repository\CourseRepository;
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
    public function new(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        $data = json_decode($request->getContent(), true);
        $course = new Course();
        $course->setTitle($data['title']);
        $course->setDescription($data['description'] ?? '');
        $course->setCapacity((int) $data['capacity']);
        
        $startTime = new \DateTime($data['startTime']);
        $course->setStartTime($startTime);
        
        $duration = (int) ($data['durationMinutes'] ?? 60);
        $course->setDurationMinutes($duration);
        
        $endTime = clone $startTime;
        $endTime->modify("+$duration minutes");
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
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        
        if ($course->getTrainer() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) $course->setTitle($data['title']);
        if (isset($data['description'])) $course->setDescription($data['description']);
        if (isset($data['capacity'])) $course->setCapacity((int) $data['capacity']);
        
        if (isset($data['startTime']) || isset($data['durationMinutes'])) {
            $startTime = isset($data['startTime']) ? new \DateTime($data['startTime']) : $course->getStartTime();
            $duration = isset($data['durationMinutes']) ? (int) $data['durationMinutes'] : $course->getDurationMinutes();
            
            $course->setStartTime($startTime);
            $course->setDurationMinutes($duration);
            
            $endTime = clone $startTime;
            $endTime->modify("+$duration minutes");
            $course->setEndTime($endTime);
        }

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
