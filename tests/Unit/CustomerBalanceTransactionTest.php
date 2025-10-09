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
            'balance_type' => StripeCustomerBalanceTransaction::TYPE_CHECKOUT_SESSION_SUBSCRIPTION_PAYMENT,
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

        $this->assertSame(StripeCustomerBalanceTransaction::TYPE_CHECKOUT_SESSION_SUBSCRIPTION_PAYMENT, $transaction->balanceType());
        $this->assertSame('cs_test_123', $transaction->checkoutSession());
        $this->assertTrue($transaction->isCheckoutSessionSubscriptionPayment());
        $this->assertFalse($transaction->isCheckoutSessionSubscriptionPaymentCanceled());
    }
}
