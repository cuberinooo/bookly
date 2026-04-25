<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Repository\BookingRepository;
use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/courses/{id}')]
class BookingController extends AbstractController
{
    #[Route('/bookings/{bookingId}', name: 'course_booking_delete', methods: ['DELETE'])]
    public function deleteBooking(Course $course, int $bookingId, BookingRepository $bookingRepository, BookingService $bookingService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        if ($course->getTrainer() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $booking = $bookingRepository->find($bookingId);
        if (!$booking || $booking->getCourse() !== $course) {
            return new JsonResponse(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        $bookingService->deleteBooking($booking);

        return new JsonResponse(['status' => 'Booking deleted by trainer']);
    }

    #[Route('/book', name: 'course_book', methods: ['POST'])]
    public function book(Course $course, BookingService $bookingService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');
        
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            [$booking, $isWaitlist] = $bookingService->book($course, $user);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $msg = $isWaitlist ? 'Added to waitlist' : 'Booking confirmed';
        return new JsonResponse(['status' => $msg], Response::HTTP_CREATED);
    }

    #[Route('/book', name: 'course_unbook', methods: ['DELETE'])]
    public function unbook(Course $course, BookingService $bookingService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');
        
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $bookingService->unbook($course, $user);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['status' => 'Booking cancelled']);
    }
}
