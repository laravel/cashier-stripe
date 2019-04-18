<?php

namespace Laravel\Cashier\Tests\Integration;

class CustomerTest extends IntegrationTestCase
{
    public function test_customers_in_stripe_can_be_updated()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');
        $user->createAsStripeCustomer();

        $customer = $user->updateStripeCustomer(['description' => 'Mohamed Said']);

        $this->assertEquals('Mohamed Said', $customer->description);
    }
}
