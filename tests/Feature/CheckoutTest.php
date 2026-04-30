<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Checkout;

class CheckoutTest extends FeatureTestCase
{
    /**
     * @param  \Illuminate\Routing\Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/home', fn () => 'Hello World!')->name('home');
    }

    public function test_customers_can_start_a_product_checkout_session()
    {
        $user = $this->createCustomer('customers_can_start_a_product_checkout_session');

        $shirtPrice = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'T-shirt',
            ],
            'unit_amount' => 1500,
        ]);

        $carPrice = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'Car',
            ],
            'unit_amount' => 30000,
        ]);

        $items = [$shirtPrice->id => 5, $carPrice->id];

        $checkout = $user->checkout($items, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_customers_can_start_a_product_checkout_session_with_a_coupon_applied()
    {
        $user = $this->createCustomer('customers_can_start_a_product_checkout_session_with_a_coupon_applied');

        $shirtPrice = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'T-shirt',
            ],
            'unit_amount' => 1500,
        ]);

        $coupon = self::stripe()->coupons->create([
            'duration' => 'repeating',
            'amount_off' => 500,
            'duration_in_months' => 3,
            'currency' => 'USD',
        ]);

        $checkout = $user->withCoupon($coupon->id)
            ->checkout($shirtPrice->id, [
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_customers_can_start_a_one_off_charge_checkout_session()
    {
        $user = $this->createCustomer('customers_can_start_a_one_off_charge_checkout_session');

        $checkout = $user->checkoutCharge(1200, 'T-shirt', 1, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_customers_can_start_a_subscription_checkout_session()
    {
        $user = $this->createCustomer('customers_can_start_a_subscription_checkout_session');

        $price = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'Forge',
            ],
            'nickname' => 'Forge Hobby',
            'recurring' => ['interval' => 'year'],
            'unit_amount' => 1500,
        ]);

        $taxRate = self::stripe()->taxRates->create([
            'display_name' => 'VAT',
            'description' => 'VAT Belgium',
            'jurisdiction' => 'BE',
            'percentage' => 21,
            'inclusive' => false,
        ]);

        $user->taxRates = [$taxRate->id];

        $checkout = $user->newSubscription('default', $price->id)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertTrue($checkout->allow_promotion_codes);
        $this->assertSame(1815, $checkout->amount_total);

        $coupon = self::stripe()->coupons->create([
            'duration' => 'repeating',
            'amount_off' => 500,
            'duration_in_months' => 3,
            'currency' => 'USD',
        ]);

        $checkout = $user->newSubscription('default', $price->id)
            ->withCoupon($coupon->id)
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertNull($checkout->allow_promotion_codes);
        $this->assertSame(1210, $checkout->amount_total);
    }

    public function test_guest_customers_can_start_a_checkout_session()
    {
        $shirtPrice = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'T-shirt',
            ],
            'unit_amount' => 1500,
        ]);

        $checkout = Checkout::guest()->create($shirtPrice->id, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_customers_can_start_an_embedded_product_checkout_session()
    {
        $user = $this->createCustomer('customers_can_start_an_embedded_product_checkout_session');

        $shirtPrice = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'T-shirt',
            ],
            'unit_amount' => 1500,
        ]);

        $items = [$shirtPrice->id => 5];

        $checkout = $user->checkout($items, [
            'ui_mode' => 'embedded',
            'return_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_customers_can_start_an_embedded_product_checkout_session_without_a_redirect()
    {
        $user = $this->createCustomer('customers_can_start_an_embedded_product_checkout_session');

        $shirtPrice = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'T-shirt',
            ],
            'unit_amount' => 1500,
        ]);

        $items = [$shirtPrice->id => 5];

        $checkout = $user->checkout($items, [
            'ui_mode' => 'embedded',
            'redirect_on_completion' => 'never',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_subscription_checkout_with_flexible_billing_mode()
    {
        $user = $this->createCustomer('subscription_checkout_flexible_billing');

        $price = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'Flexible Test Plan',
            ],
            'recurring' => ['interval' => 'month'],
            'unit_amount' => 1000,
        ]);

        $checkout = $user->newSubscription('default', $price->id)
            ->withBillingMode('flexible')
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertEquals('subscription', $checkout->mode);

        // Verify the checkout session was created successfully
        $this->assertNotNull($checkout->id);
        $this->assertNotNull($checkout->url);
    }

    public function test_subscription_checkout_with_classic_billing_mode()
    {
        $user = $this->createCustomer('subscription_checkout_classic_billing');

        $price = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'Classic Test Plan',
            ],
            'recurring' => ['interval' => 'month'],
            'unit_amount' => 1000,
        ]);

        $checkout = $user->newSubscription('default', $price->id)
            ->withBillingMode('classic')
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertEquals('subscription', $checkout->mode);

        // Verify the checkout session was created successfully
        $this->assertNotNull($checkout->id);
        $this->assertNotNull($checkout->url);
    }

    public function test_checkout_builder_with_flexible_billing_mode()
    {
        $user = $this->createCustomer('checkout_builder_flexible_billing');

        $price = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'Builder Test Plan',
            ],
            'recurring' => ['interval' => 'month'],
            'unit_amount' => 1000,
        ]);

        $checkout = Checkout::customer($user)
            ->withBillingMode('flexible')
            ->createSubscription([$price->id => 1], [
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertEquals('subscription', $checkout->mode);

        // Verify the checkout session was created successfully
        $this->assertNotNull($checkout->id);
        $this->assertNotNull($checkout->url);
    }

    public function test_checkout_billing_mode_respects_config_default()
    {
        // Set config to flexible
        config(['cashier.default_billing_mode' => 'flexible']);

        $user = $this->createCustomer('checkout_config_default_flexible');

        $price = self::stripe()->prices->create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'Config Default Test Plan',
            ],
            'recurring' => ['interval' => 'month'],
            'unit_amount' => 1000,
        ]);

        // Create checkout without explicit billing mode (should use config default)
        $checkout = $user->newSubscription('default', $price->id)
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertEquals('subscription', $checkout->mode);

        // Verify the checkout session was created successfully
        $this->assertNotNull($checkout->id);
        $this->assertNotNull($checkout->url);

        // Reset config
        config(['cashier.default_billing_mode' => 'classic']);
    }
}
