<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Http\RedirectResponse;
use Stripe\TaxId as StripeTaxId;

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

    public function test_customers_can_manage_tax_ids()
    {
        $user = $this->createCustomer('customers_can_manage_tax_ids');
        $user->createAsStripeCustomer();

        $taxId = $user->createTaxId('eu_vat', 'BE0123456789');

        $this->assertSame('eu_vat', $taxId->type);
        $this->assertSame('BE0123456789', $taxId->value);
        $this->assertSame('BE', $taxId->country);

        $taxIds = $user->taxIds();

        $this->assertCount(1, $taxIds);
        $this->assertInstanceOf(StripeTaxId::class, $taxIds->first());

        $taxId = $user->findTaxId($taxId->id);

        $this->assertSame('eu_vat', $taxId->type);
        $this->assertSame('BE0123456789', $taxId->value);

        $user->deleteTaxId($taxId->id);

        $this->assertEmpty($user->taxIds());
    }
}
