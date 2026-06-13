<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\SubscriptionService;
use PHPUnit\Framework\TestCase;
use Stripe\Service\SubscriptionService as StripeSubscriptionService;
use Stripe\StripeClient;
use Stripe\Subscription;

class SubscriptionServiceTest extends TestCase
{
    public function test_cancel_yearly_subscription_immediately(): void
    {
        // Construct a real Stripe Subscription object from an array
        $mockSubscription = Subscription::constructFrom([
            'id' => 'sub_yearly_123',
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'recurring' => [
                                'interval' => 'year',
                            ],
                        ],
                    ],
                ],
            ],
            'ended_at' => 1500000000,
        ]);

        // Setup Stripe SubscriptionService mock
        $stripeSubService = $this->createMock(StripeSubscriptionService::class);
        $stripeSubService->expects($this->once())
            ->method('retrieve')
            ->with('sub_yearly_123', [], ['stripe_account' => 'acct_test123'])
            ->willReturn($mockSubscription);

        $stripeSubService->expects($this->once())
            ->method('cancel')
            ->with('sub_yearly_123', [], ['stripe_account' => 'acct_test123'])
            ->willReturn($mockSubscription);

        // Setup StripeClient mock
        $stripeClient = $this->createMock(StripeClient::class);
        $stripeClient->method('__get')
            ->with('subscriptions')
            ->willReturn($stripeSubService);

        // Run the service call
        $service = new SubscriptionService('test_secret_key', $stripeClient);
        $result = $service->cancelSubscription('sub_yearly_123', 'acct_test123');

        // Assertions
        $this->assertEquals([
            'status' => 'success',
            'cancellation_type' => 'immediate',
            'message' => 'Your yearly subscription has been cancelled immediately.',
            'ends_at' => 1500000000,
        ], $result);
    }

    public function test_cancel_monthly_subscription_at_period_end(): void
    {
        // Construct a real Stripe Subscription object from an array
        $mockSubscription = Subscription::constructFrom([
            'id' => 'sub_monthly_123',
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'recurring' => [
                                'interval' => 'month',
                            ],
                        ],
                    ],
                ],
            ],
            'current_period_end' => 1600000000,
        ]);

        // Setup Stripe SubscriptionService mock
        $stripeSubService = $this->createMock(StripeSubscriptionService::class);
        $stripeSubService->expects($this->once())
            ->method('retrieve')
            ->with('sub_monthly_123', [], ['stripe_account' => 'acct_test123'])
            ->willReturn($mockSubscription);

        $stripeSubService->expects($this->once())
            ->method('update')
            ->with('sub_monthly_123', ['cancel_at_period_end' => true], ['stripe_account' => 'acct_test123'])
            ->willReturn($mockSubscription);

        // Setup StripeClient mock
        $stripeClient = $this->createMock(StripeClient::class);
        $stripeClient->method('__get')
            ->with('subscriptions')
            ->willReturn($stripeSubService);

        // Run the service call
        $service = new SubscriptionService('test_secret_key', $stripeClient);
        $result = $service->cancelSubscription('sub_monthly_123', 'acct_test123');

        // Assertions
        $this->assertEquals([
            'status' => 'success',
            'cancellation_type' => 'period_end',
            'message' => 'Your monthly subscription will end at the end of your current billing period.',
            'ends_at' => 1600000000,
        ], $result);
    }
}
