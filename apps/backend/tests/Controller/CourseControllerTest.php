<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseControllerTest extends WebTestCase
{
    public function testTransferCourseToAnotherTrainer(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // 1. Create original trainer
        $trainer1 = new User();
        $trainer1->setEmail('trainer' . uniqid() . '@example.com');
        $trainer1->setName('Trainer 1');
        $trainer1->setRoles(['ROLE_TRAINER']);
        $trainer1->setPassword('password');
        $trainer1->setIsVerified(true);
        $entityManager->persist($trainer1);

        // 2. Create another trainer
        $trainer2 = new User();
        $trainer2->setEmail('trainer' . uniqid() . '@example.com');
        $trainer2->setName('Trainer 2');
        $trainer2->setRoles(['ROLE_TRAINER']);
        $trainer2->setPassword('password');
        $trainer2->setIsVerified(true);
        $entityManager->persist($trainer2);

        // 3. Create a course owned by trainer1
        $course = new Course();
        $course->setTitle('Original Course');
        $course->setTrainer($trainer1);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        $entityManager->flush();

        // 4. Authenticate as trainer1
        $client->loginUser($trainer1);

        // 5. Attempt to transfer course to trainer2
        $client->request('PATCH', '/api/courses/' . $course->getId(), [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'trainerId' => $trainer2->getId()
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        // 6. Verify transfer
        $entityManager->clear();
        $updatedCourse = $entityManager->getRepository(Course::class)->find($course->getId());
        $this->assertEquals($trainer2->getId(), $updatedCourse->getTrainer()->getId());
    }

    public function testTransferCourseRemovesNewTrainerFromBookings(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // 1. Create original trainer
        $trainer1 = new User();
        $trainer1->setEmail('t1_' . uniqid() . '@example.com');
        $trainer1->setName('Trainer 1');
        $trainer1->setRoles(['ROLE_TRAINER']);
        $trainer1->setPassword('password');
        $trainer1->setIsVerified(true);
        $entityManager->persist($trainer1);

        // 2. Create another trainer who is also a participant
        $trainer2 = new User();
        $trainer2->setEmail('t2_' . uniqid() . '@example.com');
        $trainer2->setName('Trainer 2');
        $trainer2->setRoles(['ROLE_TRAINER']);
        $trainer2->setPassword('password');
        $trainer2->setIsVerified(true);
        $entityManager->persist($trainer2);

        // 3. Create a course owned by trainer1
        $course = new Course();
        $course->setTitle('Yoga with Trainer 1');
        $course->setTrainer($trainer1);
        $course->setStartTime(new \DateTime('+2 days'));
        $course->setEndTime(new \DateTime('+2 days 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        // 4. Book trainer2 as participant
        $booking = new Booking();
        $booking->setMember($trainer2);
        $booking->setCourse($course);
        $entityManager->persist($booking);

        $entityManager->flush();

        // 5. Authenticate as trainer1
        $client->loginUser($trainer1);

        // 6. Transfer course to trainer2
        $client->request('PATCH', '/api/courses/' . $course->getId(), [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'trainerId' => $trainer2->getId()
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 7. Verify transfer AND booking removal
        $entityManager->clear();
        $updatedCourse = $entityManager->getRepository(Course::class)->find($course->getId());
        $this->assertEquals($trainer2->getId(), $updatedCourse->getTrainer()->getId());
        
        $bookingExists = $entityManager->getRepository(Booking::class)->findOneBy([
            'member' => $trainer2,
            'course' => $course
        ]);
        $this->assertNull($bookingExists, 'Trainer 2 should have been removed from bookings');
    }
}
