<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['name'])) {
            return new JsonResponse(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Email already registered'], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $role = $data['role'] ?? 'ROLE_MEMBER';
        if (!in_array($role, ['ROLE_MEMBER', 'ROLE_TRAINER'])) {
            $role = 'ROLE_MEMBER';
        }
        $user->setRoles([$role]);
        $user->setIsVerified(false);
        
        $token = Uuid::v4()->toBase58();
        $user->setVerificationToken($token);

        $entityManager->persist($user);
        $entityManager->flush();

        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';
        $verificationUrl = $frontendUrl . '/verify-email?token=' . $token;

        $email = (new TemplatedEmail())
            ->from('noreply@phoenix-booking.com')
            ->to($user->getEmail())
            ->subject('Verify your Phoenix Booking account')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'name' => $user->getName(),
                'url' => $verificationUrl,
            ]);

        $mailer->send($email);

        return new JsonResponse(['status' => 'User created. Please check your email to verify your account.'], Response::HTTP_CREATED);
    }

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            return new JsonResponse(['error' => 'Missing token'], Response::HTTP_BAD_REQUEST);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Invalid token'], Response::HTTP_BAD_REQUEST);
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Email verified successfully']);
    }

    #[Route('/api/register/roles', name: 'api_register_roles', methods: ['GET'])]
    public function getRoles(): JsonResponse
    {
        return new JsonResponse([
            ['label' => 'Member', 'value' => 'ROLE_MEMBER'],
            ['label' => 'Trainer', 'value' => 'ROLE_TRAINER']
        ]);
    }
}
