<?php

namespace App\Controller;

use App\Entity\Course;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;
use App\Repository\UserRepository;
use App\Service\ApiCacheService;
use App\Service\BookingService;
use App\Service\CourseService;
use App\Service\TrainingCycleService;
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
    public function __construct(
        private readonly ApiCacheService $apiCache
    ) {
    }

    #[Route('', name: 'course_index', methods: ['GET'])]
    public function index(Request $request, CourseService $courseService): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $companyId = $user->getCompany()->getId();
        $queryParams = $request->query->all();
        
        // Context for cache includes roles and all query params
        $context = [
            'roles' => $user->getRoles(),
            'query' => $queryParams,
            'viewMode' => $request->headers->get('X-View-Mode', 'member')
        ];

        $result = $this->apiCache->get('course', $companyId, $context, function() use ($courseService, $queryParams) {
            return $courseService->listCourses($queryParams);
        }, 300);

        return new JsonResponse($result, Response::HTTP_OK);
    }

    #[Route('', name: 'course_new', methods: ['POST'])]
    public function new(Request $request, CourseService $courseService, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        $data = json_decode($request->getContent(), true);

        try {
            /** @var \App\Entity\User $creator */
            $creator = $this->getUser();
            $trainer = $creator;

            if (isset($data['trainerId'])) {
                $requestedTrainer = $userRepository->find($data['trainerId']);
                if ($requestedTrainer && in_array('ROLE_TRAINER', $requestedTrainer->getRoles())) {
                    $trainer = $requestedTrainer;
                }
            }

            $courses = $courseService->createCourseSeries($data, $trainer);
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
    public function show(int $id, CourseRepository $courseRepository, SerializerInterface $serializer, TrainingCycleService $cycleService): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $companyId = $user->getCompany()->getId();
        
        $context = [
            'id' => $id,
            'roles' => $user->getRoles()
        ];

        $data = $this->apiCache->get('course', $companyId, $context, function() use ($id, $courseRepository, $serializer, $cycleService) {
            $course = $courseRepository->find($id);
            if (!$course) {
                return null;
            }

            $data = json_decode($serializer->serialize($course, 'json', ['groups' => 'course:read']), true);
            $cycleCategory = $cycleService->getCategoryForDate($course->getUser(), $course->getStartTime());
            if ($cycleCategory) {
                $data['cycleCategory'] = $cycleCategory;
            }
            return $data;
        }, 300);

        if (!$data) {
            throw $this->createNotFoundException('Course not found');
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'course_edit', methods: ['PATCH'])]
    public function edit(Request $request, int $id, CourseRepository $courseRepository, CourseService $courseService, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        $course = $courseRepository->find($id);
        if (!$course) {
            throw $this->createNotFoundException('Course not found');
        }

        $data = json_decode($request->getContent(), true);
        $updateSeries = $request->query->getBoolean('transferAll', false);
        $seriesId = $course->getSeriesId();

        $newTrainer = null;
        if (isset($data['trainerId'])) {
            $newTrainer = $userRepository->find($data['trainerId']);
            if (!$newTrainer || !in_array('ROLE_TRAINER', $newTrainer->getRoles())) {
                return new JsonResponse(['error' => 'Invalid trainer'], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($updateSeries && $seriesId) {
            $updates = [];
            if ($newTrainer && $newTrainer->getId() !== $course->getUser()->getId()) {
                $updates['trainer'] = $newTrainer;
            }

            if (isset($data['startTime']) || isset($data['durationMinutes'])) {
                $serverTz = new \DateTimeZone(date_default_timezone_get());
                $startTime = isset($data['startTime']) ? (new \DateTime($data['startTime']))->setTimezone($serverTz) : $course->getStartTime();
                $duration = (int) (isset($data['durationMinutes']) ? $data['durationMinutes'] : ($course->getDurationMinutes() ?? 60));

                if ($startTime->format('H:i') !== $course->getStartTime()->format('H:i')) {
                    $updates['startTime'] = $startTime;
                }
                if ($duration !== $course->getDurationMinutes()) {
                    $updates['durationMinutes'] = $duration;
                }
            }

            if (isset($data['title']) && $data['title'] !== $course->getTitle()) {
                $updates['title'] = $data['title'];
            }
            if (isset($data['description']) && $data['description'] !== $course->getDescription()) {
                $updates['description'] = $data['description'];
            }
            if (isset($data['capacity']) && (int)$data['capacity'] !== $course->getCapacity()) {
                $updates['capacity'] = (int)$data['capacity'];
            }
            if (isset($data['allowTrial']) && (bool)$data['allowTrial'] !== $course->isAllowTrial()) {
                $updates['allowTrial'] = (bool)$data['allowTrial'];
            }

            try {
                $courseService->updateCourseSeries($seriesId, $updates, $course->getStartTime());
            } catch (ScheduleConflictException $e) {
                return new JsonResponse(['error' => $e->getFrontendMessage()], Response::HTTP_CONFLICT);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } else {
            try {
                $courseService->updateSingleCourse($course, $data, $newTrainer);
            } catch (ScheduleConflictException $e) {
                return new JsonResponse(['error' => $e->getFrontendMessage()], Response::HTTP_CONFLICT);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return new JsonResponse(['status' => 'Course updated']);
    }

    #[Route('/{id}', name: 'course_delete', methods: ['DELETE'])]
    public function delete(Request $request, int $id, CourseRepository $courseRepository, EntityManagerInterface $entityManager, CourseService $courseService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        $course = $courseRepository->find($id);
        if (!$course) {
            throw $this->createNotFoundException('Course not found');
        }

        $deleteAll = $request->query->getBoolean('deleteAll');
        $seriesId = $course->getSeriesId();

        if ($deleteAll && $seriesId) {
            $count = $courseService->deleteCourseSeries($seriesId);
            return new JsonResponse(['status' => "Series deleted ($count courses removed)"]);
        }

        $entityManager->remove($course);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Course deleted']);
    }

    #[Route('/{id}/postpone', name: 'course_postpone', methods: ['POST'])]
    public function postpone(Course $course, CourseService $courseService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        try {
            /** @var \App\Entity\User $trainer */
            $trainer = $this->getUser();
            $courseService->postponeCourse($course, $trainer);
            return new JsonResponse(['message' => 'Course postponed and members unbooked successfully.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
