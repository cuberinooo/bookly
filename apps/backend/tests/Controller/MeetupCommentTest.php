<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\Entity\User;
use App\Enum\RsvpStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class MeetupCommentTest extends WebTestCase
{
    private function createCompany(\Doctrine\ORM\EntityManagerInterface $em, string $name): Company
    {
        $company = new Company();
        $company->setName($name);
        $em->persist($company);

        return $company;
    }

    private function createUser(\Doctrine\ORM\EntityManagerInterface $em, Company $company, string $name, string $role = 'ROLE_MEMBER'): User
    {
        $user = new User();
        $user->setEmail(uniqid('', true).'@example.com');
        $user->setName($name);
        $user->setRoles([$role]);
        $user->setPassword('password');
        $user->setIsVerified(true);
        $user->setCompany($company);
        $em->persist($user);

        return $user;
    }

    private function getToken($client, User $user): string
    {
        return $client->getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);
    }

    public function test_meetup_comments_and_notifications(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = $this->createCompany($entityManager, 'Fitness Club '.uniqid());
        $organizer = $this->createUser($entityManager, $company, 'Organizer', 'ROLE_TRAINER');
        $memberA = $this->createUser($entityManager, $company, 'Member A');
        $memberB = $this->createUser($entityManager, $company, 'Member B');

        // 1. Create a Meetup
        $meetup = new Meetup();
        $meetup->setTitle('Community BBQ');
        $meetup->setCreator($organizer);
        $meetup->setCompany($company);
        $meetup->setLocation('Local Park');
        $entityManager->persist($meetup);

        // 2. RSVP Member A and B to the meetup
        $rsvpA = new MeetupRsvp();
        $rsvpA->setMeetup($meetup);
        $rsvpA->setUser($memberA);
        $rsvpA->setCompany($company);
        $rsvpA->setStatus(RsvpStatus::GOING);
        $entityManager->persist($rsvpA);

        $rsvpB = new MeetupRsvp();
        $rsvpB->setMeetup($meetup);
        $rsvpB->setUser($memberB);
        $rsvpB->setCompany($company);
        $rsvpB->setStatus(RsvpStatus::GOING);
        $entityManager->persist($rsvpB);

        $entityManager->flush();

        $tokenA = $this->getToken($client, $memberA);
        $tokenB = $this->getToken($client, $memberB);
        $tokenOrganizer = $this->getToken($client, $organizer);

        // 3. Member A posts a comment
        $client->request('POST', sprintf('/api/meetups/%d/comments', $meetup->getId()), [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenA,
        ], json_encode([
            'content' => 'I will bring some drinks!',
        ]));
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        // 4. Verify Member B sees 1 unread comment globally
        $client->request('GET', '/api/meetups/notifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(1, $data['totalUnread']);

        // 5. Verify Member B sees 1 unread comment in the meetups list
        $client->request('GET', '/api/meetups?filter=active', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $meetups = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(1, $meetups[0]['unreadCommentsCount']);

        // 6. Member B marks comments as read
        $client->request('POST', sprintf('/api/meetups/%d/comments/mark-read', $meetup->getId()), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 7. Verify counts are now 0 for Member B
        $client->request('GET', '/api/meetups/notifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(0, $data['totalUnread']);

        $client->request('GET', '/api/meetups?filter=active', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        $meetups = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(0, $meetups[0]['unreadCommentsCount']);

        // 8. Organizer posts a comment
        sleep(1);
        $client->request('POST', sprintf('/api/meetups/%d/comments', $meetup->getId()), [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenOrganizer,
        ], json_encode([
            'content' => 'Great, see you all there!',
        ]));

        // 9. Verify Member B sees 1 new unread comment
        sleep(1);
        $client->request('GET', '/api/meetups/notifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(1, $data['totalUnread']);
    }

    public function test_planning_phase_meetup_appears_in_active_filter(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = $this->createCompany($entityManager, 'Planning Club '.uniqid());
        $user = $this->createUser($entityManager, $company, 'Planner');
        $entityManager->flush();

        $token = $this->getToken($client, $user);

        // Create a meetup without a date (Planning Phase)
        $meetup = new Meetup();
        $meetup->setTitle('Future Ski Trip');
        $meetup->setCreator($user);
        $meetup->setCompany($company);
        $meetup->setLocation('TBD');
        $meetup->setMeetupDate(null); // Explicitly null
        $entityManager->persist($meetup);
        $entityManager->flush();

        $client->request('GET', '/api/meetups?filter=active', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $meetups = json_decode($client->getResponse()->getContent(), true);

        // Find our meetup in the list
        $found = false;
        foreach ($meetups as $m) {
            if ('Future Ski Trip' === $m['title']) {
                $found = true;
                $this->assertNull($m['meetupDate']);
                break;
            }
        }
        $this->assertTrue($found, 'Planning phase meetup should be in active list');
    }
}
