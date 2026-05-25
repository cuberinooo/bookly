<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
        private string $frontendUrl
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

        return new RedirectResponse($this->frontendUrl . '/settings?tab=stripe');
    }

    #[Route('/onboard/refresh', name: 'stripe_onboard_refresh', methods: ['GET'])]
    public function onboardRefresh(Request $request): RedirectResponse
    {
        // Just redirect them back to the settings page, they can click "Set up Payments" again
        return new RedirectResponse($this->frontendUrl . '/settings?tab=stripe');
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

        if ($setupFeeAmount <= 0 || $monthlyFeeAmount <= 0) {
            return new JsonResponse(['error' => 'Invalid amounts'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];

        // Create Setup Fee Product & Price
        $setupFeeProduct = Product::create([
            'name' => 'Aufnahmegebühr',
            'type' => 'service',
        ], $stripeAccountHeader);

        $setupFeePrice = Price::create([
            'product' => $setupFeeProduct->id,
            'unit_amount' => $setupFeeAmount,
            'currency' => 'eur',
        ], $stripeAccountHeader);

        // Create Monthly Membership Product & Price
        $membershipProduct = Product::create([
            'name' => 'Monatliche Mitgliedschaft',
            'type' => 'service',
        ], $stripeAccountHeader);

        $membershipPrice = Price::create([
            'product' => $membershipProduct->id,
            'unit_amount' => $monthlyFeeAmount,
            'currency' => 'eur',
            'recurring' => ['interval' => 'month'],
        ], $stripeAccountHeader);

        $company->setStripePriceSetupFeeId($setupFeePrice->id);
        $company->setStripePriceMembershipId($membershipPrice->id);
        $this->em->flush();

        return new JsonResponse([
            'setupFeePriceId' => $setupFeePrice->id,
            'monthlyFeePriceId' => $membershipPrice->id,
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

        $setupFeePriceId = $company->getStripePriceSetupFeeId();
        $membershipPriceId = $company->getStripePriceMembershipId();

        if (!$setupFeePriceId || !$membershipPriceId) {
            return new JsonResponse(['error' => 'Prices not configured'], 400);
        }

        $stripeAccountHeader = ['stripe_account' => $company->getStripeAccountId()];

        $successUrl = $this->frontendUrl . '/dashboard?upgrade=success';
        $cancelUrl = $this->frontendUrl . '/dashboard?upgrade=cancelled';

        $session = Session::create([
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $setupFeePriceId,
                    'quantity' => 1,
                ],
                [
                    'price' => $membershipPriceId,
                    'quantity' => 1,
                ]
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'user_id' => $user->getId(),
            ]
        ], $stripeAccountHeader);

        return new JsonResponse(['url' => $session->url]);
    }
}
