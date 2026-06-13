<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

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
                $payload,
                $sigHeader,
                $this->stripeWebhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        // Handle the checkout.session.completed event
        if ('checkout.session.completed' === $event->type) {
            $session = $event->data->object;

            if (isset($session->metadata->user_id)) {
                $userId = $session->metadata->user_id;

                $user = $this->em->getRepository(User::class)->find($userId);

                if ($user) {
                    // Promotion: Only if the user is currently a TRIAL user
                    $roles = $user->getRoles();
                    if (in_array('ROLE_TRIAL', $roles, true)) {
                        $roles = array_diff($roles, ['ROLE_TRIAL']);
                        if (!in_array('ROLE_MEMBER', $roles, true)) {
                            $roles[] = 'ROLE_MEMBER';
                        }
                        $user->setRoles(array_values($roles));
                    }

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

                            $yearlyTrialEnd = (new \DateTime('+1 year'))->getTimestamp();
                            if (isset($session->metadata->yearly_trial_end) && !empty($session->metadata->yearly_trial_end)) {
                                $metaTrial = (int) $session->metadata->yearly_trial_end;
                                if ($metaTrial > time()) {
                                    $yearlyTrialEnd = $metaTrial;
                                }
                            }

                            Subscription::create([
                                'customer' => $session->customer,
                                'items' => [['price' => $company->getStripeConfig()->getStripePriceYearlyRecurringId()]],
                                'trial_end' => $yearlyTrialEnd,
                                'description' => 'Jährliche Verwaltungsgebühr (Folgejahre)',
                                'metadata' => [
                                    'user_id' => $user->getId(),
                                    'auto_created' => 'true',
                                ],
                            ], $stripeAccountHeader);
                        } catch (\Exception $e) {
                            $this->logger->error('Failed to create background yearly subscription: '.$e->getMessage());
                        }
                    }

                    // Trigger Membership Welcome Email
                    try {
                        $this->emailService->sendMembershipWelcomeEmail($user);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to send membership welcome mail after stripe upgrade: '.$e->getMessage());
                    }
                }
            }
        }

        // Handle Subscription Deleted (Expiration)
        if ('customer.subscription.deleted' === $event->type) {
            $subscription = $event->data->object;
            $customerId = $subscription->customer;

            $user = $this->em->getRepository(User::class)->findOneBy(['stripeCustomerId' => $customerId]);
            if ($user) {
                $company = $user->getCompany();
                $stripeConfig = $company ? $company->getStripeConfig() : null;
                $stripeAccountHeader = $stripeConfig ? ['stripe_account' => $stripeConfig->getStripeAccountId()] : [];

                // Check if the deleted subscription is the monthly membership
                $isMembershipSub = false;
                if ($stripeConfig) {
                    $membershipPriceId = $stripeConfig->getStripePriceMembershipId();
                    foreach ($subscription->items->data as $item) {
                        if ($item->price->id === $membershipPriceId) {
                            $isMembershipSub = true;
                            break;
                        }
                    }
                }

                if ($isMembershipSub) {
                    // Automatically cancel the yearly recurring subscription if it exists and is active
                    if ($stripeConfig && $stripeConfig->getStripePriceYearlyRecurringId()) {
                        $yearlyPriceId = $stripeConfig->getStripePriceYearlyRecurringId();

                        try {
                            $allSubs = Subscription::all([
                                'customer' => $customerId,
                                'status' => 'active',
                            ], $stripeAccountHeader);

                            foreach ($allSubs->data as $activeSub) {
                                foreach ($activeSub->items->data as $item) {
                                    if ($item->price->id === $yearlyPriceId) {
                                        // Retrieve and cancel the subscription immediately
                                        $retrievedSub = Subscription::retrieve($activeSub->id, [], $stripeAccountHeader);
                                        $retrievedSub->cancel([], $stripeAccountHeader);
                                        $this->logger->info(sprintf('Automatically cancelled yearly recurring subscription %s for user %s because their monthly membership expired.', $activeSub->id, $user->getEmail()));
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            $this->logger->error('Failed to automatically cancel yearly recurring subscription: '.$e->getMessage());
                        }
                    }

                    // Downgrade: Remove MEMBER and TRAINER roles, set to TRIAL
                    $roles = $user->getRoles();
                    $roles = array_diff($roles, ['ROLE_MEMBER', 'ROLE_TRAINER']);
                    if (!in_array('ROLE_TRIAL', $roles, true)) {
                        $roles[] = 'ROLE_TRIAL';
                    }
                    $user->setRoles(array_values($roles));
                    $this->logger->info(sprintf('User %s subscription ended and was downgraded to ROLE_TRIAL', $user->getEmail()));
                } else {
                    $this->logger->info(sprintf('Subscription %s was deleted, but it was not the main membership subscription for customer %s.', $subscription->id, $customerId));
                }

                // Query Stripe to check if the customer has any other active or trialing subscriptions
                try {
                    $hasActiveSubscriptions = false;
                    if ($stripeConfig) {
                        $remainingSubscriptions = Subscription::all([
                            'customer' => $customerId,
                            'status' => 'active',
                            'limit' => 1,
                        ], $stripeAccountHeader);

                        $hasActiveSubscriptions = count($remainingSubscriptions->data) > 0;
                        if (!$hasActiveSubscriptions) {
                            $trialingSubscriptions = Subscription::all([
                                'customer' => $customerId,
                                'status' => 'trialing',
                                'limit' => 1,
                            ], $stripeAccountHeader);
                            $hasActiveSubscriptions = count($trialingSubscriptions->data) > 0;
                        }
                    }

                    if (!$hasActiveSubscriptions) {
                        $user->setStripeCustomerId(null);
                        $this->logger->info(sprintf('Cleared stripeCustomerId for user %s as no active or trialing subscriptions remain.', $user->getEmail()));
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Failed to query remaining subscriptions on webhook delete: '.$e->getMessage());
                }

                $this->em->flush();
            }
        }

        // Handle Invoice Payment Failed
        if ($event->type === 'invoice.payment_failed') {
            $invoice = $event->data->object;
            $customerId = $invoice instanceof \Stripe\Invoice ? $invoice->customer : ($invoice['customer'] ?? null);
            $customerId = is_string($customerId) ? $customerId : null;

            if ($customerId) {
                $user = $this->em->getRepository(User::class)->findOneBy(['stripeCustomerId' => $customerId]);
                if ($user) {
                    $this->logger->warning(sprintf('Invoice payment failed for user %s (Customer ID: %s, Invoice ID: %s)', $user->getEmail(), $customerId, $invoice->id));

                    try {
                        $this->emailService->sendPaymentFailedEmail($user);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to send payment failed email: '.$e->getMessage());
                    }
                }
            }
        }

        return new JsonResponse(['status' => 'success']);
    }
}
