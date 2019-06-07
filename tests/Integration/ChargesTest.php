<?php

namespace Laravel\Cashier\Tests\Integration;

use Stripe\Charge;
use Stripe\Error\InvalidRequest;

class ChargesTest extends IntegrationTestCase
{
    public function test_customer_can_be_charged()
    {
        $user = $this->createCustomer('customer_can_be_charged');
        $user->createAsStripeCustomer();
        $user->updateCard('tok_visa');

        $response = $user->charge(1000);

        $this->assertInstanceOf(Charge::class, $response);
        $this->assertEquals(1000, $response->amount);
        $this->assertEquals($user->stripe_id, $response->customer);
    }

    public function test_customer_cannot_be_charged_with_custom_source()
    {
        $user = $this->createCustomer('customer_can_be_charged_with_custom_source');
        $user->createAsStripeCustomer();

        $this->expectException(InvalidRequest::class);

        $user->charge(1000, ['source' => 'tok_visa']);
    }

    public function test_non_stripe_customer_can_be_charged()
    {
        $user = $this->createCustomer('non_stripe_customer_can_be_charged');

        $response = $user->charge(1000, ['source' => 'tok_visa']);

        $this->assertInstanceOf(Charge::class, $response);
        $this->assertEquals(1000, $response->amount);
        $this->assertNull($response->customer);
    }

    public function test_customer_can_be_charged_and_invoiced_immediately()
    {
        $user = $this->createCustomer('customer_can_be_charged_and_invoiced_immediately');
        $user->createAsStripeCustomer();
        $user->updateCard('tok_visa');

        $user->invoiceFor('Laravel Cashier', 1000);

        $invoice = $user->invoices()[0];
        $this->assertEquals('$10.00', $invoice->total());
        $this->assertEquals('Laravel Cashier', $invoice->invoiceItems()[0]->asStripeInvoiceItem()->description);
    }

    public function test_customer_can_be_refunded()
    {
        $user = $this->createCustomer('customer_can_be_refunded');
        $user->createAsStripeCustomer();
        $user->updateCard('tok_visa');

        $invoice = $user->invoiceFor('Laravel Cashier', 1000);
        $refund = $user->refund($invoice->charge);

        $this->assertEquals(1000, $refund->amount);
    }
}
