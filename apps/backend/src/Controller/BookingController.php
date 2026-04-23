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

        // Check capacity
        if (count($course->getBookings()) >= $course->getCapacity()) {
            return new JsonResponse(['error' => 'Course is full'], Response::HTTP_BAD_REQUEST);
        }

        $booking = new Booking();
        $booking->setMember($user);
        $booking->setCourse($course);

        $entityManager->persist($booking);

        // Notify trainer
        $notification = new Notification();
        $notification->setUser($course->getTrainer());
        $notification->setMessage(sprintf('%s has joined your course "%s"', $user->getName(), $course->getTitle()));
        $entityManager->persist($notification);

        $entityManager->flush();

        return new JsonResponse(['status' => 'Booking confirmed'], Response::HTTP_CREATED);
    }

    #[Route('/booking', name: 'course_unbook', methods: ['DELETE'])]
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
