<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class BookingAttendanceTest extends WebTestCase
{
    public function testToggleAttendance(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Attendance Test Gym ' . uniqid());
        $entityManager->persist($company);

        $trainer = new User();
        $trainer->setEmail('trainer_attendance' . uniqid() . '@example.com');
        $trainer->setName('Attendance Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        $member = new User();
        $member->setEmail('member_attendance' . uniqid() . '@example.com');
        $member->setName('Attendance Member');
        $member->setRoles(['ROLE_MEMBER']);
        $member->setPassword('password');
        $member->setIsVerified(true);
        $member->setCompany($company);
        $entityManager->persist($member);

        // Past course
        $course = new Course();
        $course->setTitle('Past Course');
        $course->setStartTime(new \DateTime('-2 hours'));
        $course->setEndTime(new \DateTime('-1 hour'));
        $course->setCapacity(10);
        $course->setUser($trainer);
        $course->setCompany($company);
        $entityManager->persist($course);

        $booking = new Booking();
        $booking->setUser($member);
        $booking->setCourse($course);
        $booking->setCompany($company);
        $booking->setAttended(true);
        $entityManager->persist($booking);

        $entityManager->flush();

        $token = $this->getToken($client, $trainer);

        // Toggle to false
        $client->request('PATCH', sprintf('/api/courses/%d/bookings/%d/attendance', $course->getId(), $booking->getId()), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['attended']);

        $entityManager->clear();
        $updatedBooking = $entityManager->getRepository(Booking::class)->find($booking->getId());
        $this->assertFalse($updatedBooking->isAttended());

        // Toggle back to true
        $client->request('PATCH', sprintf('/api/courses/%d/bookings/%d/attendance', $course->getId(), $booking->getId()), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);
        $this->assertTrue(json_decode($client->getResponse()->getContent(), true)['attended']);
    }


    public function testCannotToggleBeforeFinish(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = new Company();
        $company->setName('Attendance Fail Gym ' . uniqid());
        $entityManager->persist($company);

        $trainer = new User();
        $trainer->setEmail('trainer_fail' . uniqid() . '@example.com');
        $trainer->setName('Fail Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        $course = new Course();
        $course->setTitle('Future Course');
        $course->setStartTime(new \DateTime('+2 hours'));
        $course->setEndTime(new \DateTime('+3 hours'));
        $course->setCapacity(10);
        $course->setUser($trainer);
        $course->setCompany($company);
        $entityManager->persist($course);

        $booking = new Booking();
        $booking->setUser($trainer); // Just a user
        $booking->setCourse($course);
        $booking->setCompany($company);
        $entityManager->persist($booking);

        $entityManager->flush();

        // Verify initial state in DB
        $entityManager->clear();
        $checkId = $booking->getId();
        $initialBooking = $entityManager->getRepository(Booking::class)->find($checkId);
        $this->assertTrue($initialBooking->isAttended());

        $token = $this->getToken($client, $trainer);

        $client->request('PATCH', sprintf('/api/courses/%d/bookings/%d/attendance', $course->getId(), $booking->getId()), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    private function getToken($client, User $user): string
    {
        return $client->getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }
}
