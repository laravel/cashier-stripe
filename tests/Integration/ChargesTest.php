<?php

namespace Laravel\Cashier\Tests\Integration;

use Laravel\Cashier\Payment;
use Stripe\Error\InvalidRequest;
use Laravel\Cashier\Exceptions\PaymentActionRequired;

class ChargesTest extends IntegrationTestCase
{
    public function test_customer_can_be_charged()
    {
        $user = $this->createCustomer('customer_can_be_charged');
        $user->createAsStripeCustomer();
        $user->updateCard('tok_visa');

        $response = $user->charge(1000);

        $this->assertInstanceOf(Payment::class, $response);
        $this->assertEquals(1000, $response->rawAmount());
        $this->assertEquals($user->stripe_id, $response->customer);
    }

    public function test_customer_cannot_be_charged_without_a_payment_method()
    {
        $user = $this->createCustomer('customer_cannot_be_charged_without_a_payment_method');
        $user->createAsStripeCustomer();

        $this->expectException(InvalidRequest::class);

        $user->charge(1000);
    }

    public function test_non_stripe_customer_can_be_charged()
    {
        $user = $this->createCustomer('non_stripe_customer_can_be_charged');

        $response = $user->charge(1000, ['payment_method' => 'pm_card_visa']);

        $this->assertInstanceOf(Payment::class, $response);
        $this->assertEquals(1000, $response->rawAmount());
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
        $refund = $user->refund($invoice->payment_intent);

        $this->assertEquals(1000, $refund->amount);
    }

    public function test_charging_may_require_an_extra_action()
    {
        $user = $this->createCustomer('charging_may_require_an_extra_action');
        $user->createAsStripeCustomer();
        $user->updateCard('tok_threeDSecure2Required');

        try {
            $user->charge(1000);

            $this->fail('Expected exception '.PaymentActionRequired::class.' was not thrown.');
        } catch (PaymentActionRequired $e) {
            // Assert that the payment needs an extra action.
            $this->assertTrue($e->payment->requiresAction());

            // Assert that the payment was for the correct amount.
            $this->assertEquals(1000, $e->payment->rawAmount());
        }
    }
}
