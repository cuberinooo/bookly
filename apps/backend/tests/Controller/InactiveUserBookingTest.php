<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InactiveUserBookingTest extends WebTestCase
{
    public function test_inactive_user_cannot_book_or_unbook(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.user_password_hasher');

        $suffix = uniqid();

        // Create Company
        $company = new Company();
        $company->setName('Inactive Test Company '.$suffix);
        $entityManager->persist($company);

        // Create Trainer
        $trainer = new User();
        $trainer->setEmail('trainer_inactive_'.$suffix.'@example.com');
        $trainer->setName('Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword($hasher->hashPassword($trainer, 'password'));
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        // Create Inactive User
        $inactiveUser = new User();
        $inactiveUser->setEmail('inactive_'.$suffix.'@example.com');
        $inactiveUser->setName('Inactive Athlete');
        $inactiveUser->setRoles(['ROLE_MEMBER']);
        $inactiveUser->setPassword($hasher->hashPassword($inactiveUser, 'password'));
        $inactiveUser->setIsVerified(true);
        $inactiveUser->setIsActive(false); // Set to inactive
        $inactiveUser->setCompany($company);
        $entityManager->persist($inactiveUser);

        // Create Course
        $course = new Course();
        $course->setTitle('Standard Course');
        $course->setUser($trainer);
        $course->setCompany($company);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        $entityManager->flush();

        $authHeaders = [
            'PHP_AUTH_USER' => $inactiveUser->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ];

        // 1. Attempt to book
        $client->request('POST', '/api/courses/'.$course->getId().'/book', [], [], $authHeaders);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), 'Inactive user should be forbidden from booking');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Inactive users cannot book', $response['error']);

        // 2. Setup: manually add a booking for the user (even if they are inactive, maybe they were active before)
        // Actually, we should also test unbooking.
        // If an admin sets a user to inactive, they should still be able to unbook?
        // The requirement says: "he should not be able to book anymore but he can visit the rankings and meetups and so on. ... he now he is currently inaktiv and cant book/unbook a course anymore."
        // So unbooking should also be forbidden.

        // Manually create booking
        $booking = new \App\Entity\Booking();
        $booking->setUser($inactiveUser);
        $booking->setCourse($course);
        $booking->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($booking);
        $entityManager->flush();

        // 3. Attempt to unbook
        $client->request('DELETE', '/api/courses/'.$course->getId().'/book', [], [], $authHeaders);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), 'Inactive user should be forbidden from unbooking');
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Inactive users cannot unbook', $response['error']);
    }
}
