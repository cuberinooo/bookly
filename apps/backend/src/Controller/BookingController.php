<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\Notification;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/courses/{id}')]
class BookingController extends AbstractController
{
    #[Route('/bookings/{bookingId}', name: 'course_booking_delete', methods: ['DELETE'])]
    public function deleteBooking(Course $course, int $bookingId, EntityManagerInterface $entityManager, BookingRepository $bookingRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        if ($course->getTrainer() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $booking = $bookingRepository->find($bookingId);
        if (!$booking || $booking->getCourse() !== $course) {
            return new JsonResponse(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($booking);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Booking deleted by trainer']);
    }

    #[Route('/book', name: 'course_book', methods: ['POST'])]
    public function book(Course $course, EntityManagerInterface $entityManager, BookingRepository $bookingRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');
        $user = $this->getUser();

        // Check if already booked
        $existingBooking = $bookingRepository->findOneBy(['member' => $user, 'course' => $course]);
        if ($existingBooking) {
            return new JsonResponse(['error' => 'You already booked this course'], Response::HTTP_BAD_REQUEST);
        }

        // Waitlist logic: if count of confirmed bookings >= capacity, it's a waitlist booking
        $confirmedBookings = array_filter($course->getBookings()->toArray(), fn($b) => !$b->isWaitlist());
        $isWaitlist = count($confirmedBookings) >= $course->getCapacity();

        $booking = new Booking();
        $booking->setMember($user);
        $booking->setCourse($course);
        $booking->setWaitlist($isWaitlist);

        $entityManager->persist($booking);

        // Notify trainer
        $notification = new Notification();
        $notification->setUser($course->getTrainer());
        $statusMsg = $isWaitlist ? 'joined the waitlist for' : 'has joined';
        $notification->setMessage(sprintf('%s %s your course "%s"', $user->getName(), $statusMsg, $course->getTitle()));
        $entityManager->persist($notification);

        $entityManager->flush();

        $msg = $isWaitlist ? 'Added to waitlist' : 'Booking confirmed';
        return new JsonResponse(['status' => $msg], Response::HTTP_CREATED);
    }

    #[Route('/book', name: 'course_unbook', methods: ['DELETE'])]
    public function unbook(Course $course, EntityManagerInterface $entityManager, BookingRepository $bookingRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');
        $user = $this->getUser();

        $booking = $bookingRepository->findOneBy(['member' => $user, 'course' => $course]);
        if (!$booking) {
            return new JsonResponse(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($booking);

        // Notify trainer
        $notification = new Notification();
        $notification->setUser($course->getTrainer());
        $notification->setMessage(sprintf('%s has left your course "%s"', $user->getName(), $course->getTitle()));
        $entityManager->persist($notification);

        $entityManager->flush();

        return new JsonResponse(['status' => 'Booking cancelled']);
    }
}
