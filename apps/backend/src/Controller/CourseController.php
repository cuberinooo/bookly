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
    public function index(Request $request, CourseRepository $courseRepository, SerializerInterface $serializer): JsonResponse
    {
        if ($request->query->getBoolean('all', false)) {
            $courses = $courseRepository->findBy([], ['startTime' => 'ASC']);
            $json = $serializer->serialize($courses, 'json', ['groups' => 'course:read']);
            return new JsonResponse(json_decode($json, true), Response::HTTP_OK);
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $startDateStr = $request->query->get('startDate');
        $endDateStr = $request->query->get('endDate');

        $startDate = $startDateStr ? new \DateTime($startDateStr) : null;
        $endDate = $endDateStr ? new \DateTime($endDateStr) : null;

        $paginatedResults = $courseRepository->findPaginated($page, $limit, $startDate, $endDate);
        
        $data = $paginatedResults['data'];
        unset($paginatedResults['data']);

        $serializedData = $serializer->serialize($data, 'json', ['groups' => 'course:read']);
        
        return new JsonResponse([
            'data' => json_decode($serializedData, true),
            'meta' => $paginatedResults
        ], Response::HTTP_OK);
    }
#[Route('', name: 'course_new', methods: ['POST'])]
public function new(Request $request, CourseService $courseService): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_TRAINER');

    $data = json_decode($request->getContent(), true);

    try {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $courses = $courseService->createCourseSeries($data, $user);
    } catch (ScheduleConflictException $e) {
        return new JsonResponse(['error' => $e->getFrontendMessage()], Response::HTTP_CONFLICT);
    } catch (\Exception $e) {
        return new JsonResponse(['error' => 'An unexpected error occurred: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    return new JsonResponse([
        'status' => 'Course(s) created', 
        'count' => count($courses),
        'ids' => array_map(fn($c) => $c->getId(), $courses)
    ], Response::HTTP_CREATED);
}


    #[Route('/{id}', name: 'course_show', methods: ['GET'])]
    public function show(Course $course, SerializerInterface $serializer): JsonResponse
    {
        $json = $serializer->serialize($course, 'json', ['groups' => 'course:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'course_edit', methods: ['PATCH'])]
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager, CourseService $courseService, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        
        if ($course->getTrainer() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        $newTrainer = $course->getTrainer();
        if (isset($data['trainerId'])) {
            $newTrainer = $userRepository->find($data['trainerId']);
            if (!$newTrainer || !in_array('ROLE_TRAINER', $newTrainer->getRoles())) {
                return new JsonResponse(['error' => 'Invalid trainer'], Response::HTTP_BAD_REQUEST);
            }
        }
        
        if (isset($data['startTime']) || isset($data['durationMinutes']) || isset($data['trainerId'])) {
            $startTime = isset($data['startTime']) ? new \DateTime($data['startTime']) : $course->getStartTime();
            $duration = isset($data['durationMinutes']) ? (int) $data['durationMinutes'] : $course->getDurationMinutes();
            
            $endTime = clone $startTime;
            $endTime->modify("+$duration minutes");

            try {
                $courseService->validateSchedule($startTime, $endTime, $course->getId(), $newTrainer->getId());
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

        if (isset($data['trainerId'])) {
            $transferAll = $request->query->getBoolean('transferAll', false);
            $seriesId = $course->getSeriesId();

            if ($transferAll && $seriesId) {
                $count = $courseService->transferCourseSeries($seriesId, $newTrainer);
            } else {
                $course->setTrainer($newTrainer);
            }
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'Course updated']);
    }

    #[Route('/{id}', name: 'course_delete', methods: ['DELETE'])]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager, CourseService $courseService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        
        if ($course->getTrainer() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $deleteAll = $request->query->getBoolean('deleteAll', false);
        $seriesId = $course->getSeriesId();

        if ($deleteAll && $seriesId) {
            $count = $courseService->deleteCourseSeries($seriesId);
            return new JsonResponse(['status' => "Series deleted ($count courses removed)"]);
        }

        $entityManager->remove($course);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Course deleted']);
    }
}
