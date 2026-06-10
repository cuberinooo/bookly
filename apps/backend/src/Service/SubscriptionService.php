<?php

declare(strict_types=1);

namespace App\Service;

use Stripe\StripeClient;

class SubscriptionService
{
    private StripeClient $stripe;

    public function __construct(string $stripeSecretKey, ?StripeClient $stripe = null)
    {
        $this->stripe = $stripe ?? new StripeClient($stripeSecretKey);
    }

    /**
     * Cancels a subscription based on its billing interval.
     *
     * @param string $subscriptionId
     * @param string|null $stripeAccountId The Connect Stripe account ID, if applicable
     * @return array Information about the cancellation type and end date
     */
    public function cancelSubscription(string $subscriptionId, ?string $stripeAccountId = null): array
    {
        $options = $stripeAccountId ? ['stripe_account' => $stripeAccountId] : [];

        // 1. Fetch the subscription details from Stripe
        $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [], $options);

        // 2. Determine the billing interval (e.g., 'month' or 'year')
        $item = $subscription->items->data[0] ?? null;
        if (!$item) {
            throw new \Exception('No subscription items found.');
        }
        $interval = $item->price->recurring->interval ?? 'month';

        if ($interval === 'year') {
            // 3a. Cancel YEARLY immediately
            $cancelledSubscription = $this->stripe->subscriptions->cancel($subscriptionId, [], $options);

            return [
                'status' => 'success',
                'cancellation_type' => 'immediate',
                'message' => 'Your yearly subscription has been cancelled immediately.',
                'ends_at' => $cancelledSubscription->ended_at,
            ];
        } else {
            // 3b. Cancel MONTHLY at the end of the current period
            $updatedSubscription = $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => true,
            ], $options);

            return [
                'status' => 'success',
                'cancellation_type' => 'period_end',
                'message' => 'Your monthly subscription will end at the end of your current billing period.',
                'ends_at' => $updatedSubscription->current_period_end,
            ];
        }
    }
}
