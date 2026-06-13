<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailService;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Checkout\Session;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api/stripe')]
class StripeConnectController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $stripeSecretKey,
        private string $frontendUrl,
        private EmailService $emailService,
        private LoggerInterface $logger,
        private SubscriptionService $subscriptionService
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    #[Route('/onboard', name: 'stripe_onboard', methods: ['POST'])]
    public function onboard(UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company) {
            return new JsonResponse(['error' => 'No company associated'], 400);
        }

        if (!$company->getStripeConfig()->getStripeAccountId()) {
            // Create a Standard Stripe Connect account
            $account = Account::create([
                'type' => 'standard',
            ]);

            $company->getStripeConfig()->setStripeAccountId($account->id);
            $this->em->flush();
        }

        $accountId = $company->getStripeConfig()->getStripeAccountId();

        $returnUrl = $urlGenerator->generate('stripe_onboard_return', ['account_id' => $accountId], UrlGeneratorInterface::ABSOLUTE_URL);
        $refreshUrl = $urlGenerator->generate('stripe_onboard_refresh', ['account_id' => $accountId], UrlGeneratorInterface::ABSOLUTE_URL);

        $accountLink = AccountLink::create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return new JsonResponse(['url' => $accountLink->url]);
    }

    #[Route('/onboard/return', name: 'stripe_onboard_return', methods: ['GET'])]
    public function onboardReturn(Request $request): RedirectResponse
    {
        $accountId = $request->query->get('account_id');

        if ($accountId) {
            $account = Account::retrieve($accountId);
            if ($account->details_submitted) {
                // Find company via StripeConfig
                $stripeConfig = $this->em->getRepository(\App\Entity\StripeConfig::class)->findOneBy(['stripeAccountId' => $accountId]);
                if ($stripeConfig) {
                    $stripeConfig->setStripeOnboardingComplete(true);
                    $this->em->flush();
                }
            }
        }

        return new RedirectResponse($this->frontendUrl.'/payments');
    }

    #[Route('/onboard/refresh', name: 'stripe_onboard_refresh', methods: ['GET'])]
    public function onboardRefresh(Request $request): RedirectResponse
    {
        // Just redirect them back to the settings page, they can click "Set up Payments" again
        return new RedirectResponse($this->frontendUrl.'/payments');
    }

    #[Route('/portal-session', name: 'stripe_portal_session', methods: ['POST'])]
    public function createPortalSession(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company || !$company->getStripeConfig()->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Payments not configured'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            return new JsonResponse(['error' => 'No active billing profile found'], 404);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeConfig()->getStripeAccountId()];

        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $customerId,
                'return_url' => $this->frontendUrl.'/profile?tab=abo',
            ], $stripeAccountHeader);

            return new JsonResponse(['url' => $session->url]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/subscriptions', name: 'stripe_subscriptions', methods: ['GET'])]
    public function getSubscriptions(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company || !$company->getStripeConfig()->getStripeAccountId()) {
            return new JsonResponse(['error' => 'No stripe account associated'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeConfig()->getStripeAccountId()];

        try {
            // Fetch all active/past_due/trialing subscriptions
            $subscriptions = \Stripe\Subscription::all([
                'status' => 'all',
                'expand' => ['data.customer', 'data.latest_invoice', 'data.plan.product'],
                'limit' => 100,
            ], $stripeAccountHeader);

            $groupedByCustomer = [];
            foreach ($subscriptions->data as $sub) {
                if (in_array($sub->status, ['canceled', 'incomplete_expired'], true)) {
                    continue;
                }

                /** @var \Stripe\Customer $customer */
                $customer = $sub->customer;
                $customerId = $customer->id;

                if (!isset($groupedByCustomer[$customerId])) {
                    // Try to find the local user once per customer
                    $localUser = $this->em->getRepository(User::class)->findOneBy(['stripeCustomerId' => $customerId]);

                    $groupedByCustomer[$customerId] = [
                        'customer' => [
                            'id' => $customer->id,
                            'email' => $customer->email,
                            'name' => $customer->name,
                        ],
                        'localUser' => $localUser ? [
                            'id' => $localUser->getId(),
                            'name' => $localUser->getName(),
                        ] : null,
                        'subscriptions' => [],
                        'overall_status' => 'inactive',
                        'next_renewal' => null,
                        'latest_invoice' => null,
                    ];
                }

                $planName = $sub->plan->product->name ?? 'Unknown Plan';

                // Map status: if trialing but has an anchor, it's basically active in our context
                $displayStatus = $sub->status;
                if ('trialing' === $sub->status && $sub->billing_cycle_anchor > time()) {
                    $displayStatus = 'active'; // Treat pending alignment as active
                }

                $subData = [
                    'id' => $sub->id,
                    'status' => $sub->status,
                    'display_status' => $displayStatus,
                    'plan_name' => $planName,
                    'current_period_end' => $sub->current_period_end,
                    'cancel_at_period_end' => $sub->cancel_at_period_end,
                    'last_invoice' => $sub->latest_invoice ? [
                        'status' => $sub->latest_invoice->status,
                        'amount_paid' => $sub->latest_invoice->amount_paid / 100,
                        'currency' => $sub->latest_invoice->currency,
                        'created' => $sub->latest_invoice->created,
                    ] : null,
                ];

                $groupedByCustomer[$customerId]['subscriptions'][] = $subData;

                // Update overall status (if any sub is active, customer is active)
                if ('active' === $displayStatus) {
                    $groupedByCustomer[$customerId]['overall_status'] = 'active';
                } elseif ('active' !== $groupedByCustomer[$customerId]['overall_status'] && 'trialing' === $displayStatus) {
                    $groupedByCustomer[$customerId]['overall_status'] = 'trialing';
                } elseif ('inactive' === $groupedByCustomer[$customerId]['overall_status']) {
                    $groupedByCustomer[$customerId]['overall_status'] = $displayStatus;
                }

                // Track earliest renewal
                if (!$groupedByCustomer[$customerId]['next_renewal'] || $sub->current_period_end < $groupedByCustomer[$customerId]['next_renewal']) {
                    $groupedByCustomer[$customerId]['next_renewal'] = $sub->current_period_end;
                }
            }

            return new JsonResponse(array_values($groupedByCustomer));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/my-subscription', name: 'stripe_my_subscription', methods: ['GET'])]
    public function getMySubscription(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company || !$company->getStripeConfig()->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Payments not configured'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            return new JsonResponse(['status' => 'inactive']);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeConfig()->getStripeAccountId()];

        try {
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'all',
                'limit' => 5,
                'expand' => ['data.latest_invoice'],
            ], $stripeAccountHeader);

            if (empty($subscriptions->data)) {
                return new JsonResponse(['status' => 'inactive']);
            }

            $mappedSubs = [];
            $overallStatus = 'inactive';

            foreach ($subscriptions->data as $sub) {
                if ('canceled' === $sub->status) {
                    continue;
                }

                $displayStatus = $sub->status;
                if ('trialing' === $sub->status && $sub->billing_cycle_anchor > time()) {
                    $displayStatus = 'active';
                }

                if ('active' === $displayStatus) {
                    $overallStatus = 'active';
                }

                $mappedSubs[] = [
                    'id' => $sub->id,
                    'status' => $sub->status,
                    'display_status' => $displayStatus,
                    'cancel_at_period_end' => $sub->cancel_at_period_end,
                    'current_period_end' => $sub->current_period_end,
                    'latest_invoice' => $sub->latest_invoice ? [
                        'status' => $sub->latest_invoice->status,
                        'amount_paid' => $sub->latest_invoice->amount_paid / 100,
                        'currency' => $sub->latest_invoice->currency,
                    ] : null,
                ];
            }

            return new JsonResponse([
                'status' => $overallStatus,
                'display_status' => 'active' === $overallStatus ? 'active' : ($mappedSubs[0]['display_status'] ?? $overallStatus),
                'subscriptions' => $mappedSubs,
                // For backward compatibility take the first non-canceled
                'cancel_at_period_end' => $mappedSubs[0]['cancel_at_period_end'] ?? false,
                'current_period_end' => $mappedSubs[0]['current_period_end'] ?? null,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/my-subscription', name: 'stripe_my_subscription_cancel', methods: ['DELETE'])]
    public function cancelMySubscription(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company || !$company->getStripeConfig()->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Payments not configured'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            return new JsonResponse(['error' => 'No active subscription found'], 404);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeConfig()->getStripeAccountId()];

        try {
            // Find all non-canceled subscriptions for this customer
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'all',
            ], $stripeAccountHeader);

            if (empty($subscriptions->data)) {
                return new JsonResponse(['error' => 'No active subscription found'], 404);
            }

            $cancelledCount = 0;
            $results = [];

            foreach ($subscriptions->data as $sub) {
                if ('canceled' === $sub->status) {
                    continue;
                }

                $cancelResult = $this->subscriptionService->cancelSubscription(
                    $sub->id,
                    $company->getStripeConfig()->getStripeAccountId()
                );
                $results[] = $cancelResult;
                ++$cancelledCount;
            }

            if (0 === $cancelledCount) {
                return new JsonResponse(['error' => 'No active subscription found'], 404);
            }

            // Prioritize returning the period_end cancellation info (which represents the main monthly subscription)
            $finalResult = $results[0];
            foreach ($results as $res) {
                if ('period_end' === $res['cancellation_type']) {
                    $finalResult = $res;
                    break;
                }
            }

            return new JsonResponse($finalResult);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/prices', name: 'stripe_prices_get', methods: ['GET'])]
    public function getPrices(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $company = $user->getCompany();

        if (!$company || !$company->getStripeConfig()->getStripeAccountId()) {
            return new JsonResponse(['error' => 'No stripe account associated'], 400);
        }

        $stripeConfig = $company->getStripeConfig();
        $stripeAccountHeader = ['stripe_account' => $stripeConfig->getStripeAccountId()];
        $setupFeeId = $stripeConfig->getStripePriceSetupFeeId();
        $membershipId = $stripeConfig->getStripePriceMembershipId();

        $data = [
            'setupFee' => $stripeConfig->getSetupFeeAmount() ? $stripeConfig->getSetupFeeAmount() / 100 : null,
            'monthlyFee' => $stripeConfig->getMonthlyFeeAmount() ? $stripeConfig->getMonthlyFeeAmount() / 100 : null,
            'yearlyFeeEnabled' => $stripeConfig->isYearlyFeeEnabled(),
            'paymentEnabled' => $stripeConfig->isPaymentEnabled(),
            'billingCycleAnchorDay' => $stripeConfig->getBillingCycleAnchorDay(),
        ];

        // Fallback: If local amounts are missing but we have IDs, fetch from Stripe and update local records
        $needsFlush = false;
        if ($setupFeeId && null === $data['setupFee']) {
            try {
                $price = Price::retrieve($setupFeeId, $stripeAccountHeader);
                $stripeConfig->setSetupFeeAmount($price->unit_amount);
                $data['setupFee'] = $price->unit_amount / 100;
                $needsFlush = true;
            } catch (\Exception $e) {
            }
        }

        if ($membershipId && null === $data['monthlyFee']) {
            try {
                $price = Price::retrieve($membershipId, $stripeAccountHeader);
                $stripeConfig->setMonthlyFeeAmount($price->unit_amount);
                $data['monthlyFee'] = $price->unit_amount / 100;
                $needsFlush = true;
            } catch (\Exception $e) {
            }
        }

        if ($needsFlush) {
            $this->em->flush();
        }

        return new JsonResponse($data);
    }

    #[Route('/prices', name: 'stripe_prices', methods: ['POST'])]
    public function savePrices(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        $stripeConfig = $company->getStripeConfig();

        if (!$company || !$stripeConfig->getStripeAccountId()) {
            return new JsonResponse(['error' => 'No stripe account associated'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $setupFeeAmount = isset($data['setupFee']) ? (int) round($data['setupFee'] * 100) : 0;
        $monthlyFeeAmount = isset($data['monthlyFee']) ? (int) round($data['monthlyFee'] * 100) : 0;

        if ($monthlyFeeAmount <= 0) {
            return new JsonResponse(['error' => 'Invalid monthly fee amount'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $stripeConfig->getStripeAccountId()];

        // 1. Handle Payment Enabled Toggle & Validation
        if (isset($data['paymentEnabled'])) {
            $newValue = (bool) $data['paymentEnabled'];
            $oldValue = $stripeConfig->isPaymentEnabled();

            if ($oldValue && !$newValue) {
                // Check for ANY active/trialing/past_due subscriptions in Stripe before allowing disable
                $subscriptions = \Stripe\Subscription::all([
                    'status' => 'all',
                    'limit' => 10,
                ], $stripeAccountHeader);

                $activeCount = 0;
                foreach ($subscriptions->data as $sub) {
                    if (!in_array($sub->status, ['canceled', 'incomplete_expired'], true)) {
                        ++$activeCount;
                    }
                }

                if ($activeCount > 0) {
                    return new JsonResponse(['error' => 'Cannot disable payments while active or pending subscriptions exist.'], Response::HTTP_BAD_REQUEST);
                }
            }
            $stripeConfig->setPaymentEnabled($newValue);
        }

        // 2. Update company billing preferences
        if (isset($data['yearlyFeeEnabled'])) {
            $stripeConfig->setYearlyFeeEnabled((bool) $data['yearlyFeeEnabled']);
        }
        if (isset($data['billingCycleAnchorDay'])) {
            $stripeConfig->setBillingCycleAnchorDay(0 === $data['billingCycleAnchorDay'] ? null : (int) $data['billingCycleAnchorDay']);
        }

        // 3. Manage Membership Product & Price
        $membershipProductId = $stripeConfig->getStripeProductMembershipId();
        if (!$membershipProductId) {
            $membershipProduct = Product::create([
                'name' => 'Monatliche Mitgliedschaft',
                'type' => 'service',
            ], $stripeAccountHeader);
            $membershipProductId = $membershipProduct->id;
            $stripeConfig->setStripeProductMembershipId($membershipProductId);
        }

        $currentMembershipPriceId = $stripeConfig->getStripePriceMembershipId();
        $localMonthlyAmount = $stripeConfig->getMonthlyFeeAmount();

        $needsNewMembershipPrice = false;
        $membershipPriceChanged = false;

        if (!$currentMembershipPriceId || $localMonthlyAmount !== $monthlyFeeAmount) {
            $needsNewMembershipPrice = true;
            if ($currentMembershipPriceId) {
                $membershipPriceChanged = true;
            }
        }

        if ($needsNewMembershipPrice) {
            $membershipPrice = Price::create([
                'product' => $membershipProductId,
                'unit_amount' => $monthlyFeeAmount,
                'currency' => 'eur',
                'recurring' => ['interval' => 'month'],
            ], $stripeAccountHeader);
            $stripeConfig->setStripePriceMembershipId($membershipPrice->id);
            $stripeConfig->setMonthlyFeeAmount($monthlyFeeAmount);

            // IF PRICE CHANGED: Update existing subscribers
            if ($membershipPriceChanged) {
                try {
                    $subscriptions = \Stripe\Subscription::all([
                        'price' => $currentMembershipPriceId,
                        'status' => 'active',
                    ], $stripeAccountHeader);

                    foreach ($subscriptions->autoPagingIterator() as $sub) {
                        \Stripe\Subscription::update($sub->id, [
                            'items' => [
                                [
                                    'id' => $sub->items->data[0]->id,
                                    'price' => $membershipPrice->id,
                                ],
                            ],
                            'proration_behavior' => 'always_invoice',
                        ], $stripeAccountHeader);

                        // Notify user via Email
                        $localUser = $this->em->getRepository(User::class)->findOneBy(['stripeCustomerId' => $sub->customer]);
                        if ($localUser) {
                            $this->emailService->sendPriceChangeNotification($localUser, $monthlyFeeAmount / 100);
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Failed to update existing subscriptions after price change: '.$e->getMessage());
                }
            }
        }

        // 4. Manage Yearly Admin Fee Product & Prices
        if ($setupFeeAmount > 0) {
            $yearlyFeeProductId = $stripeConfig->getStripeProductSetupFeeId();
            if (!$yearlyFeeProductId) {
                $yearlyFeeProduct = Product::create([
                    'name' => 'Jährliche Verwaltungsgebühr',
                    'type' => 'service',
                ], $stripeAccountHeader);
                $yearlyFeeProductId = $yearlyFeeProduct->id;
                $stripeConfig->setStripeProductSetupFeeId($yearlyFeeProductId);
            }

            $currentOneTimeId = $stripeConfig->getStripePriceSetupFeeId();
            $localSetupAmount = $stripeConfig->getSetupFeeAmount();

            if (!$currentOneTimeId || $localSetupAmount !== $setupFeeAmount) {
                // A. One-Time Price (initial checkout)
                $p = Price::create(['product' => $yearlyFeeProductId, 'unit_amount' => $setupFeeAmount, 'currency' => 'eur'], $stripeAccountHeader);
                $stripeConfig->setStripePriceSetupFeeId($p->id);

                // B. Recurring Price (renewals)
                $pRec = Price::create([
                    'product' => $yearlyFeeProductId,
                    'unit_amount' => $setupFeeAmount,
                    'currency' => 'eur',
                    'recurring' => ['interval' => 'year'],
                ], $stripeAccountHeader);
                $stripeConfig->setStripePriceYearlyRecurringId($pRec->id);

                $stripeConfig->setSetupFeeAmount($setupFeeAmount);
            }
        }

        $this->em->flush();

        return new JsonResponse([
            'setupFeePriceId' => $stripeConfig->getStripePriceSetupFeeId(),
            'monthlyFeePriceId' => $stripeConfig->getStripePriceMembershipId(),
        ]);
    }

    #[Route('/checkout', name: 'stripe_checkout', methods: ['POST'])]
    public function createCheckoutSession(UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $company = $user->getCompany();
        if (!$company || !$company->getStripeConfig()->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Company not configured for payments'], 400);
        }

        // Use the ONE-TIME Setup Fee Price ID
        $setupFeePriceId = $company->getStripeConfig()->getStripePriceSetupFeeId();
        $membershipPriceId = $company->getStripeConfig()->getStripePriceMembershipId();

        if (!$membershipPriceId) {
            return new JsonResponse(['error' => 'Membership price not configured'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeConfig()->getStripeAccountId()];

        $successUrl = $this->frontendUrl.'/profile?tab=abo&upgrade=success';
        $cancelUrl = $this->frontendUrl.'/profile?tab=abo&upgrade=cancelled';

        $customerId = $user->getStripeCustomerId();
        $hasPaidYearlyFee = false;
        $yearlyTrialEnd = null;

        if ($customerId && $company->getStripeConfig()->isYearlyFeeEnabled()) {
            $yearlyPriceId = $company->getStripeConfig()->getStripePriceYearlyRecurringId();
            if ($yearlyPriceId) {
                try {
                    $subs = \Stripe\Subscription::all([
                        'customer' => $customerId,
                        'status' => 'all',
                        'limit' => 50,
                    ], $stripeAccountHeader);

                    foreach ($subs->data as $sub) {
                        $isYearly = false;
                        foreach ($sub->items->data as $item) {
                            if ($item->price->id === $yearlyPriceId) {
                                $isYearly = true;
                                break;
                            }
                        }

                        if ($isYearly) {
                            if (in_array($sub->status, ['active', 'trialing'], true)) {
                                $hasPaidYearlyFee = true;
                                $yearlyTrialEnd = $sub->trial_end ?: $sub->current_period_end;
                                break;
                            }

                            $renewalDate = $sub->trial_end ?: $sub->current_period_end;
                            if ($renewalDate > time()) {
                                $hasPaidYearlyFee = true;
                                $yearlyTrialEnd = $renewalDate;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Failed to check yearly fee status: '.$e->getMessage());
                }
            }
        }

        $lineItems = [];
        // Yearly Fee is added as a ONE-TIME line item ONLY if they haven't paid it yet this year
        if ($company->getStripeConfig()->isYearlyFeeEnabled() && $setupFeePriceId && !$hasPaidYearlyFee) {
            $lineItems[] = [
                'price' => $setupFeePriceId,
                'quantity' => 1,
            ];
        }

        // Monthly Membership is the RECURRING line item
        $lineItems[] = [
            'price' => $membershipPriceId,
            'quantity' => 1,
        ];

        $sessionData = [
            'mode' => 'subscription',
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'user_id' => $user->getId(),
                'yearly_trial_end' => $yearlyTrialEnd ? (string) $yearlyTrialEnd : '',
            ],
            'customer_email' => $user->getEmail(),
        ];

        // Handle Billing Cycle Anchor if configured
        $anchorDay = $company->getStripeConfig()->getBillingCycleAnchorDay();
        if (null !== $anchorDay) {
            $now = new \DateTime();
            $target = new \DateTime();
            $target->setDate((int) $now->format('Y'), (int) $now->format('m'), $anchorDay);

            if ($target <= $now) {
                $target->modify('+1 month');
            }

            // To use billing_cycle_anchor, we must set payment_behavior to default_incomplete or similar
            // but for Checkout it's easier to use subscription_data
            $sessionData['subscription_data'] = [
                'billing_cycle_anchor' => $target->getTimestamp(),
                'proration_behavior' => 'create_prorations',
            ];
        }

        $session = Session::create($sessionData, $stripeAccountHeader);

        return new JsonResponse(['url' => $session->url]);
    }

    #[Route('/my-subscription/reactivate', name: 'stripe_my_subscription_reactivate', methods: ['POST'])]
    public function reactivateMySubscription(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $company = $user->getCompany();
        if (!$company || !$company->getStripeConfig()->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Payments not configured'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            return new JsonResponse(['error' => 'No subscription found'], 404);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeConfig()->getStripeAccountId()];

        try {
            // Find all subscriptions for this customer
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'all',
            ], $stripeAccountHeader);

            if (empty($subscriptions->data)) {
                return new JsonResponse(['error' => 'No active subscription found'], 404);
            }

            $monthlyPriceId = $company->getStripeConfig()->getStripePriceMembershipId();
            $yearlyPriceId = $company->getStripeConfig()->getStripePriceYearlyRecurringId();

            $reactivatedCount = 0;
            $monthlySub = null;
            $yearlySub = null;
            $lastCanceledYearlySub = null;

            foreach ($subscriptions->data as $sub) {
                // Identify monthly membership subscription
                $isMonthly = false;
                $isYearly = false;
                foreach ($sub->items->data as $item) {
                    if ($item->price->id === $monthlyPriceId) {
                        $isMonthly = true;
                    }
                    if ($item->price->id === $yearlyPriceId) {
                        $isYearly = true;
                    }
                }

                if ($isMonthly && 'canceled' !== $sub->status) {
                    $monthlySub = $sub;
                }
                if ($isYearly) {
                    if (in_array($sub->status, ['active', 'trialing'], true)) {
                        $yearlySub = $sub;
                    } elseif ('canceled' === $sub->status) {
                        if (null === $lastCanceledYearlySub || $sub->created > $lastCanceledYearlySub->created) {
                            $lastCanceledYearlySub = $sub;
                        }
                    }
                }
            }

            if (!$monthlySub) {
                return new JsonResponse(['error' => 'No active membership subscription to reactivate'], 404);
            }

            // 1. Reactivate the monthly subscription (turn off cancel_at_period_end)
            if ($monthlySub->cancel_at_period_end) {
                \Stripe\Subscription::update($monthlySub->id, [
                    'cancel_at_period_end' => false,
                ], $stripeAccountHeader);
                ++$reactivatedCount;
            }

            // 2. Reactivate/Recreate the yearly subscription
            if ($company->getStripeConfig()->isYearlyFeeEnabled() && $yearlyPriceId) {
                if ($yearlySub) {
                    if ($yearlySub->cancel_at_period_end) {
                        \Stripe\Subscription::update($yearlySub->id, [
                            'cancel_at_period_end' => false,
                        ], $stripeAccountHeader);
                        ++$reactivatedCount;
                    }
                } else {
                    // Recreate it! Use original renewal date from the previous canceled yearly sub if in future
                    $trialEnd = null;
                    if ($lastCanceledYearlySub) {
                        $renewalDate = $lastCanceledYearlySub->trial_end ?: $lastCanceledYearlySub->current_period_end;
                        if ($renewalDate > time()) {
                            $trialEnd = $renewalDate;
                        }
                    }

                    if (null === $trialEnd) {
                        $trialEnd = (new \DateTime('+1 year'))->getTimestamp();
                    }

                    \Stripe\Subscription::create([
                        'customer' => $customerId,
                        'items' => [['price' => $yearlyPriceId]],
                        'trial_end' => $trialEnd,
                        'description' => 'Jährliche Verwaltungsgebühr (Folgejahre)',
                        'metadata' => [
                            'user_id' => $user->getId(),
                            'auto_created' => 'true',
                        ],
                    ], $stripeAccountHeader);
                    ++$reactivatedCount;
                }
            }

            return new JsonResponse(['status' => 'success', 'reactivated' => $reactivatedCount]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/my-invoices', name: 'stripe_my_invoices', methods: ['GET'])]
    public function getMyInvoices(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $company = $user->getCompany();
        if (!$company || !$company->getStripeConfig()->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Payments not configured'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            return new JsonResponse([]);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeConfig()->getStripeAccountId()];

        try {
            // Retrieve invoices from Stripe
            $invoices = \Stripe\Invoice::all([
                'customer' => $customerId,
                'limit' => 20,
            ], $stripeAccountHeader);

            $mappedInvoices = [];
            foreach ($invoices->data as $invoice) {
                // Determine description/title
                $description = '';
                if (!empty($invoice->lines->data)) {
                    $descriptions = [];
                    foreach ($invoice->lines->data as $line) {
                        $descriptions[] = $line->description ?: 'Subscription Item';
                    }
                    $description = implode(', ', $descriptions);
                } else {
                    $description = $invoice->description ?: 'Invoice';
                }

                $mappedInvoices[] = [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'amount_paid' => $invoice->amount_paid / 100,
                    'currency' => strtoupper($invoice->currency),
                    'status' => $invoice->status,
                    'created' => $invoice->created,
                    'invoice_pdf' => $invoice->invoice_pdf,
                    'description' => $description,
                ];
            }

            return new JsonResponse($mappedInvoices);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
