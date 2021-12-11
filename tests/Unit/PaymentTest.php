<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Payment;
use PHPUnit\Framework\TestCase;
use Stripe\PaymentIntent;
use Stripe\Subscription;

class PaymentTest extends TestCase
{
    public function test_it_can_return_its_requires_payment_method_status()
    {
        $paymentIntent = new PaymentIntent();
        $paymentIntent->status = 'requires_payment_method';
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->requiresPaymentMethod());
    }

    public function test_it_can_return_its_requires_action_status()
    {
        $paymentIntent = new PaymentIntent();
        $paymentIntent->status = 'requires_action';
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->requiresAction());
    }

    public function test_it_can_return_its_canceled_status()
    {
        $paymentIntent = new PaymentIntent();
        $paymentIntent->status = Subscription::STATUS_CANCELED;
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->isCanceled());
    }

    public function test_it_can_return_its_succeeded_status()
    {
        $paymentIntent = new PaymentIntent();
        $paymentIntent->status = 'succeeded';
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->isSucceeded());
    }
}
