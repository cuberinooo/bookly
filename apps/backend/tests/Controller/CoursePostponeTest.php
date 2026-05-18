<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CoursePostponeTest extends WebTestCase
{
    private function createCompany($entityManager): Company
    {
        $company = new Company();
        $company->setName('Test Company ' . uniqid());
        $entityManager->persist($company);
        $entityManager->flush();
        return $company;
    }

    private function createTrainer($entityManager, Company $company, $container): User
    {
        $trainer = new User();
        $trainer->setEmail('trainer' . uniqid() . '@example.com');
        $trainer->setName('Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        
        $hasher = $container->get('security.user_password_hasher');
        $hashedPassword = $hasher->hashPassword($trainer, 'password');
        $trainer->setPassword($hashedPassword);
        
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);
        $entityManager->flush();
        return $trainer;
    }

    private function createMember($entityManager, Company $company, $container): User
    {
        $member = new User();
        $member->setEmail('member' . uniqid() . '@example.com');
        $member->setName('Member');
        $member->setRoles(['ROLE_USER']);
        
        $hasher = $container->get('security.user_password_hasher');
        $hashedPassword = $hasher->hashPassword($member, 'password');
        $member->setPassword($hashedPassword);
        
        $member->setIsVerified(true);
        $member->setCompany($company);
        $entityManager->persist($member);
        $entityManager->flush();
        return $member;
    }

    public function testPostponeCourseUnbooksMembersAndExcludesFromStats(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        
        $company = $this->createCompany($entityManager);
        $trainer = $this->createTrainer($entityManager, $company, $container);
        $member = $this->createMember($entityManager, $company, $container);

        // 1. Create a past course (to show up in stats)
        $course = new Course();
        $course->setTitle('Test Course');
        $course->setUser($trainer);
        $course->setCompany($company);
        $course->setStartTime(new \DateTime('-2 hours'));
        $course->setEndTime(new \DateTime('-1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        // 2. Book member into course
        $booking = new Booking();
        $booking->setUser($member);
        $booking->setCourse($course);
        $booking->setCompany($company);
        $entityManager->persist($booking);
        
        $entityManager->flush();

        $courseId = $course->getId();
        $bookingId = $booking->getId();

        $authHeader = ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($trainer->getEmail() . ':password')];

        // 3. Check stats before postponement
        $client->request('GET', '/api/trainer/statistics', [], [], $authHeader);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $statsBefore = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(1, $statsBefore['totalCourses'], 'Course should be counted in stats before postponement');
        $this->assertEquals(1, $statsBefore['uniqueMembers'], 'Member should be counted in stats before postponement');

        // 4. Postpone course
        $client->request('POST', '/api/courses/' . $courseId . '/postpone', [], [], $authHeader);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 5. Verify course status and unbooking
        $entityManager->clear();
        $updatedCourse = $entityManager->getRepository(Course::class)->find($courseId);
        $this->assertEquals(CourseStatus::POSTPONED, $updatedCourse->getStatus());
        $this->assertEquals($trainer->getId(), $updatedCourse->getPostponedBy()->getId());

        $deletedBooking = $entityManager->getRepository(Booking::class)->find($bookingId);
        $this->assertNull($deletedBooking, 'Booking should be removed after postponement');

        // 6. Check stats after postponement
        $client->request('GET', '/api/trainer/statistics', [], [], $authHeader);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $statsAfter = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(0, $statsAfter['totalCourses'], 'Postponed course should NOT be counted in stats');
        $this->assertEquals(0, $statsAfter['uniqueMembers'], 'Unbooked member from postponed course should NOT be counted in stats');
    }
}
