<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordTest extends WebTestCase
{
    public function testForgotPasswordReproduction(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // Create a user to request password for
        $user = new User();
        $user->setEmail('test_forgot_' . uniqid() . '@example.com');
        $user->setName('Forgot User');
        $user->setRoles(['ROLE_MEMBER']);
        $user->setPassword('StrongPass123!');
        $user->setIsVerified(true);
        $entityManager->persist($user);
        $entityManager->flush();

        // Request forgot password
        $client->request('POST', '/api/forgot-password', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $user->getEmail()
        ]));

        // This is expected to succeed now
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }
}
