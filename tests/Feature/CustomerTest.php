<?php

namespace Laravel\Cashier\Tests\Feature;

class CustomerTest extends FeatureTestCase
{
    public function test_customers_in_stripe_can_be_updated()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');
        $user->createAsStripeCustomer();

        $customer = $user->updateStripeCustomer(['description' => 'Mohamed Said']);

        $this->assertEquals('Mohamed Said', $customer->description);
    }

    public function test_customers_can_visit_their_customer_portal()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');
        $user->createAsStripeCustomer();

        $url = $user->customerPortalUrl('https://example.com');

        $this->assertStringStartsWith('https://billing.stripe.com/session/', $url);
    }
}
