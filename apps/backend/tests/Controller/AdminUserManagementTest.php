<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AdminUserManagementTest extends WebTestCase
{
    private function getToken($client, User $user): string
    {
        return $client->getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    public function testAdminCreatesUserFlow(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // 1. Create Admin
        $admin = $this->createUser($entityManager, ['ROLE_ADMIN']);
        $token = $this->getToken($client, $admin);

        // 2. Admin creates a new member
        $memberEmail = 'new_member_' . uniqid('', true) . '@example.com';
        $client->request('POST', '/api/admin/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ], json_encode([
            'email' => $memberEmail,
            'name' => 'New Member',
            'password' => 'TempPass123!',
            'role' => 'ROLE_MEMBER'
        ]));

        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        // 3. Verify member state
        $entityManager->clear();
        $member = $entityManager->getRepository(User::class)->findOneBy(['email' => $memberEmail]);
        $this->assertNotNull($member);
        $this->assertTrue($member->isVerified());
        $this->assertTrue($member->isMustChangePassword());

        // 4. Verify the change-password endpoint works.
        $memberToken = $this->getToken($client, $member);
        $client->request('POST', '/api/user/change-password', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $memberToken
        ], json_encode([
            'password' => 'NewStrongPass123!'
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $responseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $responseContent);

        $newToken = $responseContent['token'];
        $payload = json_decode(base64_decode(explode('.', $newToken)[1]), true);
        $this->assertFalse($payload['mustChangePassword']);

        $entityManager->clear();
        $member = $entityManager->getRepository(User::class)->find($member->getId());
        $this->assertFalse($member->isMustChangePassword());
    }

    public function testDeleteMemberWithBookings(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // Create Admin, Member, Trainer, Course, and Booking
        $admin = $this->createUser($entityManager, ['ROLE_ADMIN']);
        $member = $this->createUser($entityManager, ['ROLE_MEMBER']);
        $trainer = $this->createUser($entityManager, ['ROLE_TRAINER']);

        $course = new Course();
        $course->setTitle('Test Course');
        $course->setUser($trainer);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        $booking = new Booking();
        $booking->setUser($member);
        $booking->setCourse($course);
        $entityManager->persist($booking);

        $entityManager->flush();

        // Login as Admin
        $token = $this->getToken($client, $admin);

        $memberId = $member->getId();
        $bookingId = $booking->getId();

        // Delete Member
        $client->request('DELETE', '/api/admin/users/' . $memberId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Verify Member and Booking are gone
        $entityManager->clear();
        $this->assertNull($entityManager->getRepository(User::class)->find($memberId));
        $this->assertNull($entityManager->getRepository(Booking::class)->find($bookingId));
    }

    public function testDeleteTrainerWithCoursesDeactivatesInstead(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $admin = $this->createUser($entityManager, ['ROLE_ADMIN']);
        $trainer = $this->createUser($entityManager, ['ROLE_TRAINER']);

        $course = new Course();
        $course->setTitle('Trainer Course');
        $course->setUser($trainer);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);
        $entityManager->flush();

        $token = $this->getToken($client, $admin);

        // Delete Trainer
        $client->request('DELETE', '/api/admin/users/' . $trainer->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Verify Trainer is still there but deactivated
        $entityManager->clear();
        $updatedTrainer = $entityManager->getRepository(User::class)->find($trainer->getId());
        $this->assertNotNull($updatedTrainer);
        $this->assertFalse($updatedTrainer->isActive());
    }

    private function createUser($em, array $roles): User
    {
        $user = new User();
        $user->setEmail('user_' . uniqid() . '@example.com');
        $user->setName('Test User');
        $user->setRoles($roles);
        $user->setPassword('StrongPass123!');
        $user->setIsVerified(true);
        $em->persist($user);
        $em->flush();
        return $user;
    }
}
