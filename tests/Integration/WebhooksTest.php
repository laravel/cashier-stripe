<?php

namespace Laravel\Cashier\Tests\Integration;

use Stripe\Plan;
use Stripe\Product;
use Illuminate\Support\Str;

class WebhooksTest extends IntegrationTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $planId;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        static::$productId = static::$stripePrefix.'product-1'.Str::random(10);
        static::$planId = static::$stripePrefix.'monthly-10-'.Str::random(10);

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
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Plan(static::$planId));
        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_subscription_is_marked_as_cancelled_when_deleted_in_stripe()
    {
        $user = $this->createCustomer('subscription_is_marked_as_cancelled_when_deleted_in_stripe');
        $subscription = $user->newSubscription('main', static::$planId)->create('tok_visa');

        $this->postJson('stripe/webhook', [
            'id' => 'foo',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => $subscription->stripe_id,
                    'customer' => $user->stripe_id,
                ],
            ],
        ])->assertOk();

        $this->assertTrue($user->fresh()->subscription('main')->cancelled());
    }
}
