<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Checkout;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Price as StripePrice;

class CheckoutTest extends FeatureTestCase
{
    public function test_customers_can_start_a_product_checkout_session()
    {
        $user = $this->createCustomer('customers_can_start_a_product_checkout_session');

        $price = StripePrice::create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'T-shirt',
            ],
            'unit_amount' => 1500,
        ]);

        $checkout = $user->checkout($price->id, 1, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertInstanceOf(StripeCheckoutSession::class, $checkout->asStripeCheckoutSession());
    }

    public function test_customers_can_start_a_one_off_charge_checkout_session()
    {
        $user = $this->createCustomer('customers_can_start_a_one_off_charge_checkout_session');

        $checkout = $user->checkoutCharge(1200, 'T-shirt', 1, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertInstanceOf(StripeCheckoutSession::class, $checkout->asStripeCheckoutSession());
    }

    public function test_customers_can_start_a_subscription_checkout_session()
    {
        $user = $this->createCustomer('customers_can_start_a_subscription_checkout_session');

        $price = StripePrice::create([
            'currency' => 'USD',
            'product_data' => [
                'name' => 'Forge',
            ],
            'nickname' => 'Forge Hobby',
            'recurring' => ['interval' => 'year'],
            'unit_amount' => 1500,
        ]);

        $checkout = $user->newSubscription('default', $price->id)->checkout([
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertInstanceOf(StripeCheckoutSession::class, $checkout->asStripeCheckoutSession());
    }
}
