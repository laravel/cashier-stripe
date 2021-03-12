<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\CardException as StripeCardException;
use Stripe\Plan;
use Stripe\Product;

class PendingUpdatesTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $planId;

    /**
     * @var string
     */
    protected static $otherPlanId;

    /**
     * @var string
     */
    protected static $premiumPlanId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$productId = static::$stripePrefix.'product-1'.Str::random(10);
        static::$planId = static::$stripePrefix.'monthly-10-'.Str::random(10);
        static::$otherPlanId = static::$stripePrefix.'monthly-10-'.Str::random(10);
        static::$premiumPlanId = static::$stripePrefix.'monthly-20-premium-'.Str::random(10);

        Product::create([
            'id' => static::$productId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ]);

        Plan::create([
            'id' => static::$planId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 1000,
            'product' => static::$productId,
        ]);

        Plan::create([
            'id' => static::$otherPlanId,
            'nickname' => 'Monthly $10 Other',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 1000,
            'product' => static::$productId,
        ]);

        Plan::create([
            'id' => static::$premiumPlanId,
            'nickname' => 'Monthly $20 Premium',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 2000,
            'product' => static::$productId,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Plan(static::$planId));
        static::deleteStripeResource(new Plan(static::$otherPlanId));
        static::deleteStripeResource(new Plan(static::$premiumPlanId));
        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_subscription_can_error_if_incomplete()
    {
        $user = $this->createCustomer('subscription_can_error_if_incomplete');

        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        // Set a faulty card as the customer's default payment method.
        $user->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');

        try {
            // Attempt to swap and pay with a faulty card.
            $subscription = $subscription->errorIfPaymentFails()->swapAndInvoice(static::$premiumPlanId);

            $this->fail('Expected exception '.StripeCardException::class.' was not thrown.');
        } catch (StripeCardException $e) {
            // Assert that the plan was not swapped.
            $this->assertEquals(static::$planId, $subscription->refresh()->stripe_plan);

            // Assert subscription is active.
            $this->assertTrue($subscription->active());
        }
    }

    // public function test_subscription_can_be_pending_if_incomplete()
    // {
    //     $user = $this->createCustomer('subscription_can_be_pending_if_incomplete');
    //
    //     $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');
    //
    //     // Set a faulty card as the customer's default payment method.
    //     $user->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');
    //
    //     try {
    //         // Attempt to swap and pay with a faulty card.
    //         $subscription = $subscription->pendingIfPaymentFails()->swapAndInvoice(static::$premiumPlanId);
    //
    //         $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
    //     } catch (IncompletePayment $e) {
    //         // Assert that the payment needs an extra action.
    //         $this->assertTrue($e->payment->requiresAction());
    //
    //         // Assert that the plan was not swapped.
    //         $this->assertEquals(static::$planId, $subscription->refresh()->stripe_plan);
    //
    //         // Assert subscription is active.
    //         $this->assertTrue($subscription->active());
    //
    //         // Assert subscription has pending updates.
    //         $this->assertTrue($subscription->pending());
    //
    //         // Void the last invoice to cancel any pending updates.
    //         $subscription->latestInvoice()->void();
    //
    //         // Assert subscription has no more pending updates.
    //         $this->assertFalse($subscription->pending());
    //     }
    // }
}
