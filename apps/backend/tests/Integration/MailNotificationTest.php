<?php

namespace App\Tests\Integration;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\User;
use App\Entity\GlobalSettings;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailNotificationTest extends WebTestCase
{
    private ?HttpClientInterface $httpClient = null;

    private function clearMailhogMessages(): void
    {
        $this->httpClient->request('DELETE', 'http://mailhog:8025/api/v1/messages');
    }

    private function getMailhogMessages(): array
    {
        $response = $this->httpClient->request('GET', 'http://mailhog:8025/api/v2/messages');
        return $response->toArray()['items'] ?? [];
    }

    public function testBookingSendsConfirmationEmail(): void
    {
        $client = static::createClient();
        $this->httpClient = static::getContainer()->get(HttpClientInterface::class);
        $this->clearMailhogMessages();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.password_hasher');

        $suffix = uniqid();

        $company = new Company();
        $company->setName('Email Test Company ' . $suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $entityManager->persist($settings);
        $company->setGlobalSettings($settings);

        $trainer = new User();
        $trainer->setEmail('trainer_mail_' . $suffix . '@example.com');
        $trainer->setName('Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword($hasher->hashPassword($trainer, 'password'));
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        $user = new User();
        $user->setEmail('member_mail_' . $suffix . '@example.com');
        $user->setName('Member');
        $user->setRoles(['ROLE_MEMBER']);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $user->setIsVerified(true);
        $user->setCompany($company);
        $entityManager->persist($user);

        $course = new Course();
        $course->setTitle('Email Course');
        $course->setUser($trainer);
        $course->setCompany($company);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        $entityManager->flush();

        $authHeaders = [
            'PHP_AUTH_USER' => $user->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ];

        $client->request('POST', '/api/courses/' . $course->getId() . '/book', [], [], $authHeaders);
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $messages = $this->getMailhogMessages();
        $this->assertCount(1, $messages, 'One email should have been sent to Mailhog');
        
        $message = $messages[0];
        $this->assertEquals('Booking Confirmed: Email Course', $message['Content']['Headers']['Subject'][0]);
        $this->assertStringContainsString($user->getEmail(), $message['Content']['Headers']['To'][0]);
    }

    public function testWaitlistPromotionSendsEmails(): void
    {
        $client = static::createClient();
        $this->httpClient = static::getContainer()->get(HttpClientInterface::class);
        $this->clearMailhogMessages();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.password_hasher');

        $suffix = uniqid();

        $company = new Company();
        $company->setName('Waitlist Test Company ' . $suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $entityManager->persist($settings);
        $company->setGlobalSettings($settings);

        $trainer = new User();
        $trainer->setEmail('trainer_wait_' . $suffix . '@example.com');
        $trainer->setName('Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword($hasher->hashPassword($trainer, 'password'));
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        // User 1 (Confirmed)
        $user1 = new User();
        $user1->setEmail('user1_' . $suffix . '@example.com');
        $user1->setName('User 1');
        $user1->setRoles(['ROLE_MEMBER']);
        $user1->setPassword($hasher->hashPassword($user1, 'password'));
        $user1->setIsVerified(true);
        $user1->setCompany($company);
        $entityManager->persist($user1);

        // User 2 (Waitlisted)
        $user2 = new User();
        $user2->setEmail('user2_' . $suffix . '@example.com');
        $user2->setName('User 2');
        $user2->setRoles(['ROLE_MEMBER']);
        $user2->setPassword($hasher->hashPassword($user2, 'password'));
        $user2->setIsVerified(true);
        $user2->setCompany($company);
        $entityManager->persist($user2);

        // Course with capacity 1
        $course = new Course();
        $course->setTitle('Waitlist Course');
        $course->setUser($trainer);
        $course->setCompany($company);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(1);
        $entityManager->persist($course);

        $entityManager->flush();

        // 1. User 1 books (Confirmed)
        $client->request('POST', '/api/courses/' . $course->getId() . '/book', [], [], [
            'PHP_AUTH_USER' => $user1->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        // 2. User 2 books (Waitlist)
        $client->request('POST', '/api/courses/' . $course->getId() . '/book', [], [], [
            'PHP_AUTH_USER' => $user2->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ]);
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $this->clearMailhogMessages();

        // 3. User 1 unbooks -> User 2 should be promoted and receive email
        $client->request('DELETE', '/api/courses/' . $course->getId() . '/book', [], [], [
            'PHP_AUTH_USER' => $user1->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $messages = $this->getMailhogMessages();
        
        // We expect: 
        // 1. Cancellation email for User 1
        // 2. Waitlist Promotion email for User 2
        // 3. Booking Confirmation email for User 2
        $this->assertCount(3, $messages, 'Three emails should have been sent (1 cancellation, 2 promotion/confirmation)');

        $subjects = array_map(fn($m) => $m['Content']['Headers']['Subject'][0], $messages);
        $this->assertContains('Booking Cancelled: Waitlist Course', $subjects);
        $this->assertContains('Spot Available: Waitlist Course', $subjects);
        $this->assertContains('Booking Confirmed: Waitlist Course', $subjects);
    }

    public function testTrainerDeletingBookingSendsEmail(): void
    {
        $client = static::createClient();
        $this->httpClient = static::getContainer()->get(HttpClientInterface::class);
        $this->clearMailhogMessages();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.password_hasher');

        $suffix = uniqid();

        $company = new Company();
        $company->setName('Trainer Delete Company ' . $suffix);
        $entityManager->persist($company);

        $trainer = new User();
        $trainer->setEmail('trainer_del_' . $suffix . '@example.com');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword($hasher->hashPassword($trainer, 'password'));
        $trainer->setName('Trainer');
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        $user = new User();
        $user->setEmail('member_del_' . $suffix . '@example.com');
        $user->setRoles(['ROLE_MEMBER']);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $user->setName('Member');
        $user->setIsVerified(true);
        $user->setCompany($company);
        $entityManager->persist($user);

        $course = new Course();
        $course->setTitle('Yoga');
        $course->setCapacity(10);
        $course->setUser($trainer);
        $course->setCompany($company);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $entityManager->persist($course);

        $entityManager->flush();

        // 1. User books
        $client->request('POST', '/api/courses/' . $course->getId() . '/book', [], [], [
            'PHP_AUTH_USER' => $user->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ]);
        
        $bookingId = json_decode($client->getResponse()->getContent(), true); // Wait, book returns status msg
        // Need to find the booking ID
        $booking = $entityManager->getRepository(\App\Entity\Booking::class)->findOneBy(['user' => $user, 'course' => $course]);

        $this->clearMailhogMessages();

        // 2. Trainer deletes booking
        $client->request('DELETE', sprintf('/api/courses/%d/bookings/%d', $course->getId(), $booking->getId()), [], [], [
            'PHP_AUTH_USER' => $trainer->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $messages = $this->getMailhogMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals('Booking Cancelled: Yoga', $messages[0]['Content']['Headers']['Subject'][0]);
        $this->assertStringContainsString($user->getEmail(), $messages[0]['Content']['Headers']['To'][0]);
    }

    public function testAdminResettingPasswordSendsEmail(): void
    {
        $client = static::createClient();
        $this->httpClient = static::getContainer()->get(HttpClientInterface::class);
        $this->clearMailhogMessages();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.password_hasher');

        $suffix = uniqid();

        $company = new Company();
        $company->setName('Password Reset Company ' . $suffix);
        $entityManager->persist($company);

        $admin = new User();
        $admin->setEmail('admin_reset_' . $suffix . '@example.com');
        $admin->setName('Admin Name');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'password'));
        $admin->setIsVerified(true);
        $admin->setCompany($company);
        $entityManager->persist($admin);

        $user = new User();
        $user->setEmail('athlete_reset_' . $suffix . '@example.com');
        $user->setRoles(['ROLE_MEMBER']);
        $user->setPassword($hasher->hashPassword($user, 'old_password'));
        $user->setName('Athlete Name');
        $user->setIsVerified(true);
        $user->setCompany($company);
        $entityManager->persist($user);

        $entityManager->flush();
        $initialPasswordHash = $user->getPassword();

        // 1. Admin resets password
        $client->request('POST', sprintf('/api/admin/users/%d/reset-password', $user->getId()), [], [], [
            'PHP_AUTH_USER' => $admin->getEmail(),
            'PHP_AUTH_PW'   => 'password',
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 2. Check Mailhog
        $messages = $this->getMailhogMessages();
        $this->assertCount(1, $messages, 'One email should have been sent for password reset');
        
        $message = $messages[0];
        $this->assertEquals('Account Security: Password Reset by Administrator', $message['Content']['Headers']['Subject'][0]);
        $this->assertStringContainsString($user->getEmail(), $message['Content']['Headers']['To'][0]);
        
        // 3. Verify user state in DB
        $entityManager->clear(); // Clear cache to get fresh state from DB
        /** @var User $updatedUser */
        $updatedUser = $entityManager->getRepository(User::class)->find($user->getId());
        
        $this->assertTrue($updatedUser->isMustChangePassword(), 'User should be forced to change password');
        $this->assertNotEquals($initialPasswordHash, $updatedUser->getPassword(), 'Password hash should have changed in the database');
    }

    public function testRegistrationNotifiesOnlyOwnCompanyAdmins(): void
    {
        $client = static::createClient();
        $this->httpClient = static::getContainer()->get(HttpClientInterface::class);
        $this->clearMailhogMessages();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.password_hasher');

        $suffix = uniqid();

        // 1. Setup Company A and its Admin
        $companyA = new Company();
        $companyA->setName('Company A ' . $suffix);
        $entityManager->persist($companyA);

        $adminA = new User();
        $adminA->setEmail('adminA_' . $suffix . '@example.com');
        $adminA->setName('Admin A');
        $adminA->setRoles(['ROLE_ADMIN']);
        $adminA->setPassword($hasher->hashPassword($adminA, 'password'));
        $adminA->setIsVerified(true);
        $adminA->setCompany($companyA);
        $entityManager->persist($adminA);

        // 2. Setup Company B and its Admin
        $companyB = new Company();
        $companyB->setName('Company B ' . $suffix);
        $entityManager->persist($companyB);

        $adminB = new User();
        $adminB->setEmail('adminB_' . $suffix . '@example.com');
        $adminB->setName('Admin B');
        $adminB->setRoles(['ROLE_ADMIN']);
        $adminB->setPassword($hasher->hashPassword($adminB, 'password'));
        $adminB->setIsVerified(true);
        $adminB->setCompany($companyB);
        $entityManager->persist($adminB);

        $entityManager->flush();

        // 3. Register a new user for Company A
        $registrationData = [
            'email' => 'newuser_' . $suffix . '@example.com',
            'password' => 'SecurePassword123!',
            'name' => 'New User',
            'companyName' => $companyA->getName()
        ];

        $client->request('POST', '/api/register', [], [], [], json_encode($registrationData));
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        // 4. Verify Mailhog messages
        $messages = $this->getMailhogMessages();
        
        // We expect at least:
        // 1. Welcome email to the new user
        // 2. Admin notification email
        
        $adminNotificationFound = false;
        foreach ($messages as $message) {
            if ($message['Content']['Headers']['Subject'][0] === 'New User Registration: New User') {
                $recipients = $message['Content']['Headers']['To'][0];
                $this->assertStringContainsString($adminA->getEmail(), $recipients, 'Admin A should be notified');
                $this->assertStringNotContainsString($adminB->getEmail(), $recipients, 'Admin B should NOT be notified');
                $adminNotificationFound = true;
            }
        }
        
        $this->assertTrue($adminNotificationFound, 'Admin notification email was not found');
    }

    public function testRegistrationWithGender(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $email = 'gender_test' . uniqid() . '@example.com';
        $registrationData = [
            'email' => $email,
            'password' => 'Password123!',
            'name' => 'Gender Athlete',
            'gender' => 'other',
            'companyName' => 'Gender Gym'
        ];

        $client->request('POST', '/api/register', [], [], [], json_encode($registrationData));
        $this->assertEquals(201, $client->getResponse()->getStatusCode());

        $user = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $this->assertEquals('other', $user->getGender()->value);
    }
}
