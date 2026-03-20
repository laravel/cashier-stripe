<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;

/**
 * Tests that billing_mode flows correctly through checkout sessions.
 */
class FlexibleBillingCheckoutTest extends FeatureTestCase
{
    protected static $productId;
    protected static $priceId;

    protected function defineRoutes($router): void
    {
        $router->get('/home', fn () => 'Hello World!')->name('home');
    }

    public static function setUpBeforeClass(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

        parent::setUpBeforeClass();

        static::$productId = self::stripe()->products->create([
            'name' => 'Checkout Flex Test '.time(),
            'type' => 'service',
        ])->id;

        static::$priceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10 Checkout',
            'currency' => 'USD',
            'recurring' => ['interval' => 'month'],
            'unit_amount' => 1000,
        ])->id;
    }

    protected function tearDown(): void
    {
        Cashier::$defaultBillingMode = 'classic';
        parent::tearDown();
    }

    public function test_subscription_checkout_with_flexible_billing_mode()
    {
        $user = $this->createCustomer('checkout-flex');

        $checkout = $user->newSubscription('default', static::$priceId)
            ->withBillingMode('flexible')
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('subscription', $checkout->mode);
    }

    public function test_subscription_checkout_with_classic_billing_mode()
    {
        $user = $this->createCustomer('checkout-classic');

        $checkout = $user->newSubscription('default', static::$priceId)
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('subscription', $checkout->mode);
    }

    public function test_subscription_checkout_with_global_flexible_default()
    {
        Cashier::defaultBillingMode('flexible');

        $user = $this->createCustomer('checkout-global-flex');

        $checkout = $user->newSubscription('default', static::$priceId)
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('subscription', $checkout->mode);
    }

    public function test_flexible_checkout_rejects_billing_thresholds()
    {
        $user = $this->createCustomer('checkout-flex-threshold');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Flexible billing mode is not compatible with billing thresholds.');

        $user->newSubscription('default', static::$priceId)
            ->withBillingMode('flexible')
            ->withBillingThresholds(['amount_gte' => 1000])
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);
    }
}
