<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Payment;

class ChargesTest extends FeatureTestCase
{
    public function test_customer_can_be_charged()
    {
        $user = $this->createCustomer('customer_can_be_charged');
        $user->createAsStripeCustomer();

        $response = $user->charge(1000, 'pm_card_visa');

        $this->assertInstanceOf(Payment::class, $response);
        $this->assertEquals(1000, $response->rawAmount());
        $this->assertEquals($user->stripe_id, $response->customer);
    }

    public function test_non_stripe_customer_can_be_charged()
    {
        $user = $this->createCustomer('non_stripe_customer_can_be_charged');

        $response = $user->charge(1000, 'pm_card_visa');

        $this->assertInstanceOf(Payment::class, $response);
        $this->assertEquals(1000, $response->rawAmount());
        $this->assertNull($response->customer);
    }

    public function test_customer_can_pay()
    {
        $user = $this->createCustomer('customer_can_pay');
        $user->createAsStripeCustomer();

        $response = $user->pay(1000);

        $this->assertInstanceOf(Payment::class, $response);
        $this->assertEquals(1000, $response->rawAmount());
        $this->assertEquals($user->stripe_id, $response->customer);
        $this->assertTrue($response->requiresPaymentMethod());
        $this->assertTrue($response->automatic_payment_methods->enabled);

        // Payment intent can be retrieved...
        $payment = $user->findPayment($response->id);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($response->id, $payment->id);
    }

    public function test_customer_can_be_charged_and_invoiced_immediately()
    {
        $user = $this->createCustomer('customer_can_be_charged_and_invoiced_immediately');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');

        $user->invoiceFor('Laravel Cashier', 1000);

        $invoice = $user->invoices()[0];
        $this->assertEquals('$10.00', $invoice->total());
        $this->assertEquals('Laravel Cashier', $invoice->invoiceItems()[0]->asStripeInvoiceLineItem()->description);
    }

    public function test_customer_can_be_refunded()
    {
        $user = $this->createCustomer('customer_can_be_refunded');
        $user->createAsStripeCustomer();
        $user->updateDefaultPaymentMethod('pm_card_visa');

        $invoice = $user->invoiceFor('Laravel Cashier', 1000);
        $refund = $user->refund($invoice->payment_intent);

        $this->assertEquals(1000, $refund->amount);
    }

    public function test_charging_may_require_an_extra_action()
    {
        $user = $this->createCustomer('charging_may_require_an_extra_action');
        $user->createAsStripeCustomer();

        try {
            $user->charge(1000, 'pm_card_threeDSecure2Required');

            $this->fail('Expected exception '.IncompletePayment::class.' was not thrown.');
        } catch (IncompletePayment $e) {
            // Assert that the payment needs an extra action.
            $this->assertTrue($e->payment->requiresAction());

            // Assert that the payment was for the correct amount.
            $this->assertEquals(1000, $e->payment->rawAmount());
        }
    }
}
