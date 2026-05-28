<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Service\BookingService;
use App\Service\CourseService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/courses/{id}')]
class BookingController extends AbstractController
{
    private function resolveCourse(string $id, CourseRepository $courseRepository, CourseService $courseService): Course
    {
        if (str_starts_with($id, 'v_')) {
            $parts = explode('_', $id);
            $seriesId = (int) $parts[1];
            $timestamp = (int) $parts[2];
            $startTime = (new \DateTime())->setTimestamp($timestamp);

            return $courseService->instantiateVirtualCourse($seriesId, $startTime);
        }

        $course = $courseRepository->find((int) $id);
        if (!$course) {
            throw $this->createNotFoundException('Course not found');
        }

        return $course;
    }

    #[Route('/bookings/{bookingId}', name: 'course_booking_delete', methods: ['DELETE'])]
    public function deleteBooking(
        string $id,
        #[MapEntity(id: 'bookingId')] Booking $booking,
        CourseRepository $courseRepository,
        CourseService $courseService,
        BookingService $bookingService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        $course = $this->resolveCourse($id, $courseRepository, $courseService);

        if ($course->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        if ($booking->getCourse() !== $course) {
            return new JsonResponse(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        $bookingService->deleteBooking($booking);

        return new JsonResponse(['status' => 'Booking deleted by trainer']);
    }

    #[Route('/bookings/{bookingId}/attendance', name: 'course_booking_attendance_toggle', methods: ['PATCH'])]
    public function toggleAttendance(
        string $id,
        #[MapEntity(id: 'bookingId')] Booking $booking,
        CourseRepository $courseRepository,
        CourseService $courseService,
        BookingService $bookingService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        $course = $this->resolveCourse($id, $courseRepository, $courseService);

        if ($course->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        if ($course->getEndTime() > new \DateTime()) {
            return new JsonResponse(['error' => 'Attendance can only be managed after the course has finished'], Response::HTTP_BAD_REQUEST);
        }

        if ($booking->getCourse() !== $course) {
            return new JsonResponse(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        $bookingService->toggleAttendance($booking);

        return $this->json(['status' => 'Attendance status updated', 'attended' => $booking->isAttended()]);
    }

    #[Route('/book', name: 'course_book', methods: ['POST'])]
    public function book(string $id, CourseRepository $courseRepository, CourseService $courseService, BookingService $bookingService): JsonResponse
    {
        if (!$this->isGranted('ROLE_TRIAL') && !$this->isGranted('ROLE_MEMBER') && !$this->isGranted('ROLE_TRAINER')) {
            throw $this->createAccessDeniedException();
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user->isActive()) {
            return new JsonResponse(['error' => 'Inactive users cannot book courses.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $course = $this->resolveCourse($id, $courseRepository, $courseService);
            [$booking, $isWaitlist] = $bookingService->book($course, $user);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $msg = $isWaitlist ? 'Added to waitlist' : 'Booking confirmed';

        return new JsonResponse(['status' => $msg], Response::HTTP_CREATED);
    }

    #[Route('/book', name: 'course_unbook', methods: ['DELETE'])]
    public function unbook(string $id, CourseRepository $courseRepository, CourseService $courseService, BookingService $bookingService): JsonResponse
    {
        if (!$this->isGranted('ROLE_TRIAL') && !$this->isGranted('ROLE_MEMBER') && !$this->isGranted('ROLE_TRAINER')) {
            throw $this->createAccessDeniedException();
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user->isActive()) {
            return new JsonResponse(['error' => 'Inactive users cannot unbook courses.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $course = $this->resolveCourse($id, $courseRepository, $courseService);
            $bookingService->unbook($course, $user);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['status' => 'Booking cancelled']);
    }
}
