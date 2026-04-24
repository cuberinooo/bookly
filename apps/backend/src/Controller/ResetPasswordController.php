<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class ResetPasswordController extends AbstractController
{
    #[Route('/api/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'Missing email'], Response::HTTP_BAD_REQUEST);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        // We don't want to leak if a user exists or not
        if ($user) {
            $token = Uuid::v4()->toBase58();
            $user->setPasswordResetToken($token);
            $user->setPasswordResetTokenExpiresAt(new \DateTime('+1 hour'));
            $entityManager->flush();

            $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';
            $resetUrl = $frontendUrl . '/reset-password?token=' . $token;

            $emailMessage = new TemplatedEmail()
                ->from($_ENV['NO_REPLAY_MAIL'])
                ->to($user->getEmail())
                ->subject('Reset your Phoenix Booking password')
                ->htmlTemplate('emails/reset_password.html.twig')
                ->context([
                    'name' => $user->getName(),
                    'url' => $resetUrl,
                ]);

            $mailer->send($emailMessage);
        }

        return new JsonResponse(['status' => 'If your email is registered, you will receive a reset link shortly.']);
    }

    #[Route('/api/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $password = $data['password'] ?? null;

        if (!$token || !$password) {
            return new JsonResponse(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['passwordResetToken' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Invalid token'], Response::HTTP_BAD_REQUEST);
        }

        if ($user->getPasswordResetTokenExpiresAt() < new \DateTime()) {
            return new JsonResponse(['error' => 'Token expired'], Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Invalidate token
        $user->setPasswordResetToken(null);
        $user->setPasswordResetTokenExpiresAt(null);

        $entityManager->flush();

        return new JsonResponse(['status' => 'Password reset successfully']);
    }
}
