<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Company;
use App\Entity\Course;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Message\CheckCourseAutoCancelMessage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AutoCancelMailTest extends WebTestCase
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

    public function test_auto_cancel_sends_email_to_trainer(): void
    {
        $client = static::createClient();
        $this->httpClient = static::getContainer()->get(HttpClientInterface::class);
        $this->clearMailhogMessages();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.password_hasher');
        $messageBus = static::getContainer()->get(MessageBusInterface::class);

        $suffix = uniqid();

        $company = new Company();
        $company->setName('AutoCancel Company '.$suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $settings->setAutoCancelEnabled(true);
        $settings->setAutoCancelMinParticipants(3);
        $settings->setAutoCancelHoursBefore(4);
        $entityManager->persist($settings);
        
        $company->setGlobalSettings($settings);

        $trainer = new User();
        $trainer->setEmail('trainer_auto_cancel_'.$suffix.'@example.com');
        $trainer->setName('Trainer AutoCancel');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword($hasher->hashPassword($trainer, 'password'));
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        $course = new Course();
        $course->setTitle('Yoga Morning');
        $course->setUser($trainer);
        $course->setCompany($company);
        // Set course to start in 3 hours (inside the 4 hour window)
        $course->setStartTime(new \DateTime('+3 hours'));
        $course->setEndTime(new \DateTime('+4 hours'));
        $course->setCapacity(10);
        $entityManager->persist($course);

        $entityManager->flush();

        // Dispatch the auto-cancel message directly
        $messageBus->dispatch(new CheckCourseAutoCancelMessage($course->getId()));

        // The handler is executed synchronously in test environment because MESSENGER_TRANSPORT_DSN=sync://

        // Check Mailhog
        $messages = $this->getMailhogMessages();
        
        // Assert we have at least 1 message
        $this->assertGreaterThanOrEqual(1, count($messages), 'Email should have been sent to Mailhog');

        // Find the specific message
        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message['Content']['Headers']['Subject'][0], 'Automatic Cancellation: Yoga Morning')) {
                $found = true;
                $this->assertStringContainsString($trainer->getEmail(), $message['Content']['Headers']['To'][0]);
                break;
            }
        }
        
        $this->assertTrue($found, 'Auto cancel email should be found in Mailhog');
    }
}
