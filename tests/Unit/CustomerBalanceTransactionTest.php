<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\CustomerBalanceTransaction;
use PHPUnit\Framework\TestCase;
use Stripe\CustomerBalanceTransaction as StripeCustomerBalanceTransaction;

class CustomerBalanceTransactionTest extends TestCase
{
    public function test_balance_type_and_checkout_session_are_exposed()
    {
        $stripeTransaction = StripeCustomerBalanceTransaction::constructFrom([
            'id' => 'cbtxn_test_123',
            'object' => 'customer_balance_transaction',
            'balance_type' => 'checkout_session_subscription_payment',
            'checkout_session' => 'cs_test_123',
            'amount' => 1000,
            'currency' => 'usd',
            'customer' => 'cus_test_456',
            'ending_balance' => 1000,
        ], []);

        $owner = new class
        {
            public $stripe_id = 'cus_test_456';
        };

        $transaction = new CustomerBalanceTransaction($owner, $stripeTransaction);

        $this->assertSame('checkout_session_subscription_payment', $transaction->balanceType());
        $this->assertSame('cs_test_123', $transaction->checkoutSession());
        $this->assertTrue($transaction->isCheckoutSessionSubscriptionPayment());
        $this->assertFalse($transaction->isCheckoutSessionSubscriptionPaymentCanceled());
    }

    public function test_invoice_is_null_when_the_transaction_has_no_invoice()
    {
        $stripeTransaction = StripeCustomerBalanceTransaction::constructFrom([
            'id' => 'cbtxn_test_123',
            'object' => 'customer_balance_transaction',
            'amount' => -500,
            'currency' => 'usd',
            'customer' => 'cus_test_456',
            'invoice' => null,
        ], []);

        $owner = new class
        {
            public $stripe_id = 'cus_test_456';
        };

        $transaction = new CustomerBalanceTransaction($owner, $stripeTransaction);

        $this->assertNull($transaction->invoice());
    }
}
