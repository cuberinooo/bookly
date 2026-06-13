<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\GlobalSettings;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripeWebhookControllerTest extends WebTestCase
{
    private ?HttpClientInterface $httpClient = null;

    private function clearMailhogMessages(): void
    {
        $this->httpClient->request('DELETE', 'http://mailhog:8025/api/v1/messages');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getMailhogMessages(): array
    {
        $response = $this->httpClient->request('GET', 'http://mailhog:8025/api/v2/messages');

        return $response->toArray()['items'] ?? [];
    }

    public function test_invoice_payment_failed_sends_email(): void
    {
        $client = static::createClient();
        $this->httpClient = static::getContainer()->get(HttpClientInterface::class);
        $this->clearMailhogMessages();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $hasher = static::getContainer()->get('security.password_hasher');

        $suffix = uniqid();

        // Setup company & settings
        $company = new Company();
        $company->setName('Payment Failed Gym '.$suffix);
        $entityManager->persist($company);

        $settings = new GlobalSettings();
        $settings->setCompany($company);
        $entityManager->persist($settings);
        $company->setGlobalSettings($settings);

        // Setup user with Stripe Customer ID
        $user = new User();
        $user->setEmail('member_failed_'.$suffix.'@example.com');
        $user->setName('John Failed');
        $user->setRoles(['ROLE_MEMBER']);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $user->setIsVerified(true);
        $user->setCompany($company);
        $user->setStripeCustomerId('cus_failed_'.$suffix);
        $entityManager->persist($user);

        $entityManager->flush();

        // Prepare failed payment webhook payload
        $payload = json_encode([
            'id' => 'evt_failed_test_'.$suffix,
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_failed_'.$suffix,
                    'customer' => 'cus_failed_'.$suffix,
                ],
            ],
        ]);

        $timestamp = time();
        $secret = 'whsec_dummy';
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $signatureHeader = sprintf('t=%d,v1=%s', $timestamp, $signature);

        // Send POST request to webhook connect endpoint
        $client->request('POST', '/webhook/stripe/connect', [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signatureHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Check Mailhog to see if payment failed notification email was sent
        $messages = $this->getMailhogMessages();
        $this->assertNotEmpty($messages, 'At least one email should have been sent to Mailhog');

        $found = false;
        foreach ($messages as $msg) {
            if ($msg['Content']['Headers']['Subject'][0] === 'Action Required: Payment Failed for Payment Failed Gym '.$suffix) {
                $this->assertStringContainsString('member_failed_'.$suffix.'@example.com', $msg['Content']['Headers']['To'][0]);
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Payment failed email should be received in Mailhog');
    }
}
