<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Event;
use Stripe\Webhook;
use Stripe\Stripe;
use Stripe\Subscription;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;

#[Route('/webhook/stripe')]
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $stripeWebhookSecret,
        private string $stripeSecretKey,
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    #[Route('/connect', name: 'stripe_webhook_connect', methods: ['POST'])]
    public function handleConnectWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        $event = null;

        try {
            $event = Webhook::constructEvent(
                $payload, $sigHeader, $this->stripeWebhookSecret
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        // Handle the checkout.session.completed event
        if ($event->type == 'checkout.session.completed') {
            $session = $event->data->object;

            if (isset($session->metadata->user_id)) {
                $userId = $session->metadata->user_id;

                $user = $this->em->getRepository(User::class)->find($userId);

                if ($user) {
                    // Update user's roles: remove ROLE_TRIAL, add ROLE_MEMBER
                    $roles = $user->getRoles();
                    $roles = array_diff($roles, ['ROLE_TRIAL']);
                    if (!in_array('ROLE_MEMBER', $roles)) {
                        $roles[] = 'ROLE_MEMBER';
                    }
                    $user->setRoles(array_values($roles));

                    // Save stripe customer ID for future reference
                    if (isset($session->customer)) {
                        $user->setStripeCustomerId($session->customer);
                    }

                    $this->em->flush();

                    // SILENT YEARLY SUBSCRIPTION CREATION
                    $company = $user->getCompany();
                    if ($company && $company->getStripeConfig()->isYearlyFeeEnabled() && $company->getStripeConfig()->getStripePriceYearlyRecurringId() && isset($session->customer)) {
                        try {
                            $stripeAccountHeader = ['stripe_account' => $company->getStripeConfig()->getStripeAccountId()];
                            $oneYearFromNow = (new \DateTime('+1 year'))->getTimestamp();

                            Subscription::create([
                                'customer' => $session->customer,
                                'items' => [['price' => $company->getStripeConfig()->getStripePriceYearlyRecurringId()]],
                                'trial_end' => $oneYearFromNow,
                                'description' => 'Jährliche Verwaltungsgebühr (Folgejahre)',
                                'metadata' => [
                                    'user_id' => $user->getId(),
                                    'auto_created' => 'true'
                                ]
                            ], $stripeAccountHeader);
                        } catch (\Exception $e) {
                            $this->logger->error('Failed to create background yearly subscription: ' . $e->getMessage());
                        }
                    }

                    // strict rule: trigger the standard "welcome mail" to the user.
                    // Do not create or send a special role-restricted version.
                    try {
                        $this->emailService->sendCompanySpecificWelcomeEmail($user);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to send welcome mail after stripe upgrade: ' . $e->getMessage());
                    }
                }
            }
        }

        // Handle Subscription Deleted (Expiration)
        if ($event->type == 'customer.subscription.deleted') {
            $subscription = $event->data->object;
            $customerId = $subscription->customer;

            $user = $this->em->getRepository(User::class)->findOneBy(['stripeCustomerId' => $customerId]);
            if ($user) {
                // Downgrade back to trial or basic role
                $roles = $user->getRoles();
                $roles = array_diff($roles, ['ROLE_MEMBER']);
                if (!in_array('ROLE_TRIAL', $roles)) {
                    $roles[] = 'ROLE_TRIAL';
                }
                $user->setRoles(array_values($roles));
                
                // Clear the stripe customer ID as the subscription is completely gone
                $user->setStripeCustomerId(null);
                
                $this->em->flush();
                $this->logger->info(sprintf('User %s subscription ended and was downgraded to ROLE_TRIAL', $user->getEmail()));
            }
        }

        return new JsonResponse(['status' => 'success']);
    }
}
