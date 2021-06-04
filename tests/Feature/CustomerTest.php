<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Http\RedirectResponse;

class CustomerTest extends FeatureTestCase
{
    public function test_customers_in_stripe_can_be_updated()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');

        $customer = $user->createAsStripeCustomer();

        $this->assertEquals('Main Str. 1', $customer->address->line1);
        $this->assertEquals('Little Rock', $customer->address->city);
        $this->assertEquals('72201', $customer->address->postal_code);

        $customer = $user->updateStripeCustomer(['description' => 'Mohamed Said']);

        $this->assertEquals('Mohamed Said', $customer->description);
    }

    public function test_customer_details_can_be_synced_with_stripe()
    {
        $user = $this->createCustomer('customer_details_can_be_synced_with_stripe');
        $user->createAsStripeCustomer();

        $user->name = 'Mohamed Said';
        $user->email = 'mohamed@example.com';
        $user->phone = '+32 499 00 00 00';

        $customer = $user->syncStripeCustomerDetails();

        $this->assertEquals('Mohamed Said', $customer->name);
        $this->assertEquals('mohamed@example.com', $customer->email);
        $this->assertEquals('+32 499 00 00 00', $customer->phone);
        $this->assertEquals('Main Str. 1', $customer->address->line1);
        $this->assertEquals('Little Rock', $customer->address->city);
        $this->assertEquals('72201', $customer->address->postal_code);
    }

    public function test_customers_can_generate_a_billing_portal_url()
    {
        $user = $this->createCustomer('customers_can_generate_a_billing_portal_url');
        $user->createAsStripeCustomer();

        $url = $user->billingPortalUrl('https://example.com');

        $this->assertStringStartsWith('https://billing.stripe.com/session/', $url);
    }

    public function test_customers_can_be_redirected_to_their_billing_portal()
    {
        $user = $this->createCustomer('customers_can_be_redirected_to_their_billing_portal');
        $user->createAsStripeCustomer();

        $response = $user->redirectToBillingPortal('https://example.com');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringStartsWith('https://billing.stripe.com/session/', $response->getTargetUrl());
    }
}
