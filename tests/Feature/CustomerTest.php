<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Http\RedirectResponse;

class CustomerTest extends FeatureTestCase
{
    public function test_customers_in_stripe_can_be_updated()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');
        $user->createAsStripeCustomer();

        $customer = $user->updateStripeCustomer(['description' => 'Mohamed Said']);

        $this->assertEquals('Mohamed Said', $customer->description);
    }

    public function test_customers_can_generate_a_billing_portal_url()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');
        $user->createAsStripeCustomer();

        $url = $user->billingPortalUrl('https://example.com');

        $this->assertStringStartsWith('https://billing.stripe.com/session/', $url);
    }

    public function test_customers_can_be_redirected_to_their_billing_portal()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');
        $user->createAsStripeCustomer();

        $response = $user->redirectToBillingPortal('https://example.com');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringStartsWith('https://billing.stripe.com/session/', $response->getTargetUrl());
    }
}
