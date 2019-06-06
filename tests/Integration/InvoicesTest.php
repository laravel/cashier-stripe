<?php

namespace Laravel\Cashier\Tests\Integration;

use Stripe\Invoice;
use Laravel\Cashier\Exceptions\InvalidStripeCustomer;

class InvoicesTest extends IntegrationTestCase
{
    public function test_require_stripe_customer_for_invoicing()
    {
        $user = $this->createCustomer('require_stripe_customer_for_invoicing');

        $this->expectException(InvalidStripeCustomer::class);

        $user->invoice();
    }

    public function test_invoicing_fails_with_nothing_to_invoice()
    {
        $user = $this->createCustomer('invoicing_fails_with_nothing_to_invoice');
        $user->createAsStripeCustomer();
        $user->updateCard('tok_visa');

        $response = $user->invoice();

        $this->assertFalse($response);
    }

    public function test_customer_can_be_invoiced()
    {
        $user = $this->createCustomer('customer_can_be_invoiced');
        $user->createAsStripeCustomer();
        $user->updateCard('tok_visa');

        $response = $user->invoiceFor('Laracon', 49900);

        $this->assertInstanceOf(Invoice::class, $response);
        $this->assertEquals(49900, $response->total);
    }
}
