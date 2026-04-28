<?php

namespace App\Controller;

use App\Entity\Course;
use App\Exception\ScheduleConflictException;
use App\Repository\CourseRepository;
use App\Repository\UserRepository;
use App\Service\BookingService;
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
        $startDateStr = $request->query->get('startDate');
        $endDateStr = $request->query->get('endDate');
        $serverTz = new \DateTimeZone(date_default_timezone_get());
        $startDate = $startDateStr ? (new \DateTime($startDateStr))->setTimezone($serverTz) : null;
        $endDate = $endDateStr ? (new \DateTime($endDateStr))->setTimezone($serverTz) : null;
        $futureOnly = $request->query->getBoolean('futureOnly', false);
        $trainerId = $request->query->get('trainerId') ? $request->query->getInt('trainerId') : null;
        $memberId = $request->query->get('memberId') ? $request->query->getInt('memberId') : null;

        if ($request->query->getBoolean('all', false)) {
            $qb = $courseRepository->createQueryBuilder('c');
            if ($futureOnly && !$startDate) {
                $qb->andWhere('c.endTime >= :now')
                   ->setParameter('now', new \DateTime());
            } elseif ($startDate) {
                $qb->andWhere('c.endTime >= :startDate')
                   ->setParameter('startDate', $startDate);
            }

            if ($endDate) {
                $qb->andWhere('c.startTime <= :endDate')
                   ->setParameter('endDate', $endDate);
            }

            if ($trainerId) {
                $qb->andWhere('c.trainer = :trainerId')
                   ->setParameter('trainerId', $trainerId);
            }

            if ($memberId) {
                $qb->join('c.bookings', 'b')
                   ->andWhere('b.member = :memberId')
                   ->setParameter('memberId', $memberId);
            }

            $courses = $qb->orderBy('c.startTime', 'ASC')
               ->getQuery()
               ->getResult();

            $json = $serializer->serialize($courses, 'json', ['groups' => 'course:read']);
            return new JsonResponse(json_decode($json, true), Response::HTTP_OK);
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        $paginatedResults = $courseRepository->findPaginated($page, $limit, $startDate, $endDate, $futureOnly, $trainerId, $memberId);

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
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager, CourseService $courseService, UserRepository $userRepository, BookingService $bookingService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');
        $data = json_decode($request->getContent(), true);
        $updateSeries = $request->query->getBoolean('transferAll', false);
        $seriesId = $course->getSeriesId();

        $updates = [];
        $newTrainer = $course->getTrainer();

        if (isset($data['trainerId'])) {
            $newTrainer = $userRepository->find($data['trainerId']);
            if (!$newTrainer || !in_array('ROLE_TRAINER', $newTrainer->getRoles())) {
                return new JsonResponse(['error' => 'Invalid trainer'], Response::HTTP_BAD_REQUEST);
            }
            if ($newTrainer->getId() !== $course->getTrainer()->getId()) {
                $updates['trainer'] = $newTrainer;
            }
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

        if ($updateSeries && $seriesId) {
            try {
                $courseService->updateCourseSeries($seriesId, $updates, $course->getStartTime());
            } catch (ScheduleConflictException $e) {
                return new JsonResponse(['error' => $e->getFrontendMessage()], Response::HTTP_CONFLICT);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } else {
            // Update only this course
            if (isset($updates['trainer'])) {
                $course->setTrainer($newTrainer);
                $bookingService->removeBookingIfExists($course, $newTrainer);
            }

            if (isset($data['startTime']) || isset($data['durationMinutes'])) {
                $serverTz = new \DateTimeZone(date_default_timezone_get());
                $startTime = isset($data['startTime']) ? (new \DateTime($data['startTime']))->setTimezone($serverTz) : $course->getStartTime();
                $duration = (int) (isset($data['durationMinutes']) ? $data['durationMinutes'] : ($course->getDurationMinutes() ?? 60));

                $endTime = clone $startTime;
                $endTime->modify("+$duration minutes");

                try {
                    $courseService->validateSchedule($startTime, $endTime, $course->getId(), $newTrainer->getId());
                } catch (ScheduleConflictException $e) {
                    return new JsonResponse(['error' => $e->getFrontendMessage()], Response::HTTP_CONFLICT);
                }

                $course->setStartTime($startTime);
                $course->setDurationMinutes($duration);
                $course->setEndTime($endTime);
            }

            if (isset($data['title'])) $course->setTitle($data['title']);
            if (isset($data['description'])) $course->setDescription($data['description']);
            if (isset($data['capacity'])) $course->setCapacity((int) $data['capacity']);
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'Course updated']);
    }

    #[Route('/{id}', name: 'course_delete', methods: ['DELETE'])]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager, CourseService $courseService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

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
