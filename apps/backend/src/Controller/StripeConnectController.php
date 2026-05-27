<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Stripe;
use Stripe\Price;
use Stripe\Product;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private LoggerInterface $logger
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

        if (!$company->getStripeAccountId()) {
            // Create a Standard Stripe Connect account
            $account = Account::create([
                'type' => 'standard',
            ]);

            $company->setStripeAccountId($account->id);
            $this->em->flush();
        }

        $accountId = $company->getStripeAccountId();

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
                // Find company
                $company = $this->em->getRepository(\App\Entity\Company::class)->findOneBy(['stripeAccountId' => $accountId]);
                if ($company) {
                    $company->setStripeOnboardingComplete(true);
                    $this->em->flush();
                }
            }
        }

        return new RedirectResponse($this->frontendUrl . '/payments');
    }

    #[Route('/onboard/refresh', name: 'stripe_onboard_refresh', methods: ['GET'])]
    public function onboardRefresh(Request $request): RedirectResponse
    {
        // Just redirect them back to the settings page, they can click "Set up Payments" again
        return new RedirectResponse($this->frontendUrl . '/settings?tab=stripe');
    }

    #[Route('/portal-session', name: 'stripe_portal_session', methods: ['POST'])]
    public function createPortalSession(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company || !$company->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Payments not configured'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            return new JsonResponse(['error' => 'No active billing profile found'], 404);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];

        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $customerId,
                'return_url' => $this->frontendUrl . '/profile?tab=abo',
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

        if (!$company || !$company->getStripeAccountId()) {
            return new JsonResponse(['error' => 'No stripe account associated'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];

        try {
            // Fetch all subscriptions for this connected account
            $subscriptions = \Stripe\Subscription::all([
                'expand' => ['data.customer', 'data.latest_invoice'],
                'limit' => 100,
            ], $stripeAccountHeader);

            $mappedSubscriptions = [];
            foreach ($subscriptions->data as $sub) {
                /** @var \Stripe\Customer $customer */
                $customer = $sub->customer;

                // Try to find the local user
                $localUser = $this->em->getRepository(User::class)->findOneBy(['stripeCustomerId' => $customer->id]);

                $mappedSubscriptions[] = [
                    'id' => $sub->id,
                    'status' => $sub->status,
                    'current_period_end' => $sub->current_period_end,
                    'customer' => [
                        'id' => $customer->id,
                        'email' => $customer->email,
                        'name' => $customer->name,
                    ],
                    'localUser' => $localUser ? [
                        'id' => $localUser->getId(),
                        'name' => $localUser->getName(),
                    ] : null,
                    'latest_invoice' => $sub->latest_invoice ? [
                        'status' => $sub->latest_invoice->status,
                        'amount_paid' => $sub->latest_invoice->amount_paid / 100,
                        'currency' => $sub->latest_invoice->currency,
                        'created' => $sub->latest_invoice->created,
                    ] : null,
                ];
            }

            return new JsonResponse($mappedSubscriptions);
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

        if (!$company || !$company->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Payments not configured'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            return new JsonResponse(['status' => 'inactive']);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];

        try {
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'all',
                'limit' => 1,
                'expand' => ['data.latest_invoice'],
            ], $stripeAccountHeader);

            if (empty($subscriptions->data)) {
                return new JsonResponse(['status' => 'inactive']);
            }

            $sub = $subscriptions->data[0];

            return new JsonResponse([
                'id' => $sub->id,
                'status' => $sub->status,
                'cancel_at_period_end' => $sub->cancel_at_period_end,
                'current_period_end' => $sub->current_period_end,
                'latest_invoice' => $sub->latest_invoice ? [
                    'status' => $sub->latest_invoice->status,
                    'amount_paid' => $sub->latest_invoice->amount_paid / 100,
                    'currency' => $sub->latest_invoice->currency,
                ] : null,
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

        if (!$company || !$company->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Payments not configured'], 400);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            return new JsonResponse(['error' => 'No active subscription found'], 404);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];

        try {
            // Find all active/past_due subscriptions for this customer
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'active', // Also consider 'past_due'
            ], $stripeAccountHeader);

            if (empty($subscriptions->data)) {
                return new JsonResponse(['error' => 'No active subscription found'], 404);
            }

            foreach ($subscriptions->data as $sub) {
                // Cancel at the end of the period
                \Stripe\Subscription::update($sub->id, [
                    'cancel_at_period_end' => true,
                ], $stripeAccountHeader);
            }

            return new JsonResponse(['status' => 'cancelled_at_period_end']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/prices', name: 'stripe_prices_get', methods: ['GET'])]
    public function getPrices(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company || !$company->getStripeAccountId()) {
            return new JsonResponse(['error' => 'No stripe account associated'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];
        $setupFeeId = $company->getStripePriceSetupFeeId();
        $membershipId = $company->getStripePriceMembershipId();

        $data = [
            'setupFee' => null,
            'monthlyFee' => null,
            'yearlyFeeEnabled' => $company->isYearlyFeeEnabled(),
            'billingCycleAnchorDay' => $company->getBillingCycleAnchorDay(),
        ];

        if ($setupFeeId) {
            try {
                $price = Price::retrieve($setupFeeId, $stripeAccountHeader);
                $data['setupFee'] = $price->unit_amount / 100;
            } catch (\Exception $e) {
                // Price might have been deleted in Stripe
            }
        }

        if ($membershipId) {
            try {
                $price = Price::retrieve($membershipId, $stripeAccountHeader);
                $data['monthlyFee'] = $price->unit_amount / 100;
            } catch (\Exception $e) {
                // Price might have been deleted in Stripe
            }
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

        if (!$company || !$company->getStripeAccountId()) {
            return new JsonResponse(['error' => 'No stripe account associated'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $setupFeeAmount = isset($data['setupFee']) ? (int)round($data['setupFee'] * 100) : 0;
        $monthlyFeeAmount = isset($data['monthlyFee']) ? (int)round($data['monthlyFee'] * 100) : 0;

        if ($monthlyFeeAmount <= 0) {
            return new JsonResponse(['error' => 'Invalid monthly fee amount'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];

        // Update company billing preferences
        if (isset($data['yearlyFeeEnabled'])) {
            $company->setYearlyFeeEnabled((bool)$data['yearlyFeeEnabled']);
        }
        if (isset($data['billingCycleAnchorDay'])) {
            $company->setBillingCycleAnchorDay($data['billingCycleAnchorDay'] === 0 ? null : (int)$data['billingCycleAnchorDay']);
        }

        // 1. Manage Membership Product & Price
        $membershipProductId = $company->getStripeProductMembershipId();
        if (!$membershipProductId) {
            $membershipProduct = Product::create([
                'name' => 'Monatliche Mitgliedschaft',
                'type' => 'service',
            ], $stripeAccountHeader);
            $membershipProductId = $membershipProduct->id;
            $company->setStripeProductMembershipId($membershipProductId);
        }

        $currentMembershipPriceId = $company->getStripePriceMembershipId();
        $needsNewMembershipPrice = true;
        $membershipPriceChanged = false;
        if ($currentMembershipPriceId) {
            try {
                $currentPrice = Price::retrieve($currentMembershipPriceId, $stripeAccountHeader);
                if ($currentPrice->unit_amount === $monthlyFeeAmount) {
                    $needsNewMembershipPrice = false;
                } else {
                    $membershipPriceChanged = true;
                }
            } catch (\Exception $e) {}
        }

        if ($needsNewMembershipPrice) {
            $membershipPrice = Price::create([
                'product' => $membershipProductId,
                'unit_amount' => $monthlyFeeAmount,
                'currency' => 'eur',
                'recurring' => ['interval' => 'month'],
            ], $stripeAccountHeader);
            $company->setStripePriceMembershipId($membershipPrice->id);

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
                    $this->logger->error('Failed to update existing subscriptions after price change: ' . $e->getMessage());
                }
            }
        }

        // 2. Manage Yearly Admin Fee Product & Prices
        if ($setupFeeAmount > 0) {
            $yearlyFeeProductId = $company->getStripeProductSetupFeeId();
            if (!$yearlyFeeProductId) {
                $yearlyFeeProduct = Product::create([
                    'name' => 'Jährliche Verwaltungsgebühr',
                    'type' => 'service',
                ], $stripeAccountHeader);
                $yearlyFeeProductId = $yearlyFeeProduct->id;
                $company->setStripeProductSetupFeeId($yearlyFeeProductId);
            }

            // A. One-Time Price (initial checkout)
            $currentOneTimeId = $company->getStripePriceSetupFeeId();
            $needsNewOneTime = true;
            if ($currentOneTimeId) {
                try {
                    $p = Price::retrieve($currentOneTimeId, $stripeAccountHeader);
                    if ($p->unit_amount === $setupFeeAmount) $needsNewOneTime = false;
                } catch (\Exception $e) {}
            }
            if ($needsNewOneTime) {
                $p = Price::create(['product' => $yearlyFeeProductId, 'unit_amount' => $setupFeeAmount, 'currency' => 'eur'], $stripeAccountHeader);
                $company->setStripePriceSetupFeeId($p->id);
            }

            // B. Recurring Price (renewals)
            $currentRecurringId = $company->getStripePriceYearlyRecurringId();
            $needsNewRecurring = true;
            if ($currentRecurringId) {
                try {
                    $p = Price::retrieve($currentRecurringId, $stripeAccountHeader);
                    if ($p->unit_amount === $setupFeeAmount) $needsNewRecurring = false;
                } catch (\Exception $e) {}
            }
            if ($needsNewRecurring) {
                $p = Price::create([
                    'product' => $yearlyFeeProductId,
                    'unit_amount' => $setupFeeAmount,
                    'currency' => 'eur',
                    'recurring' => ['interval' => 'year']
                ], $stripeAccountHeader);
                $company->setStripePriceYearlyRecurringId($p->id);
            }
        }

        $this->em->flush();

        return new JsonResponse([
            'setupFeePriceId' => $company->getStripePriceSetupFeeId(),
            'monthlyFeePriceId' => $company->getStripePriceMembershipId(),
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
        if (!$company || !$company->getStripeAccountId()) {
            return new JsonResponse(['error' => 'Company not configured for payments'], 400);
        }

        // Use the ONE-TIME Setup Fee Price ID
        $setupFeePriceId = $company->getStripePriceSetupFeeId();
        $membershipPriceId = $company->getStripePriceMembershipId();

        if (!$membershipPriceId) {
            return new JsonResponse(['error' => 'Membership price not configured'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];

        $successUrl = $this->frontendUrl . '/profile?upgrade=success';
        $cancelUrl = $this->frontendUrl . '/profile?upgrade=cancelled';

        $lineItems = [];
        // Yearly Fee is added as a ONE-TIME line item
        if ($company->isYearlyFeeEnabled() && $setupFeePriceId) {
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
            ],
            'customer_email' => $user->getEmail(),
        ];

        // Handle Billing Cycle Anchor if configured
        $anchorDay = $company->getBillingCycleAnchorDay();
        if ($anchorDay !== null) {
            $now = new \DateTime();
            $target = new \DateTime();
            $target->setDate((int)$now->format('Y'), (int)$now->format('m'), $anchorDay);

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
}
