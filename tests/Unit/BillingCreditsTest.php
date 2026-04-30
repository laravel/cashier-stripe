<?php

namespace Laravel\Cashier\Tests\Unit;

use PHPUnit\Framework\TestCase;

class BillingCreditsTest extends TestCase
{
    public function test_billing_credit_balance()
    {
        $billable = new TestBillableForCredits();
        $billable->setBalance(['usd' => -500]); // Negative means credit

        $balance = $billable->billingCreditBalance('usd');

        $this->assertEquals(-500, $balance);
    }

    public function test_billing_credit_balance_returns_zero_when_no_balance()
    {
        $billable = new TestBillableForCredits();
        $billable->setBalance([]);

        $balance = $billable->billingCreditBalance('usd');

        $this->assertEquals(0, $balance);
    }

    public function test_all_billing_credit_balances()
    {
        $expectedBalances = ['usd' => -1000, 'eur' => -250];
        $billable = new TestBillableForCredits();
        $billable->setBalance($expectedBalances);

        $balances = $billable->allBillingCreditBalances();

        $this->assertEquals($expectedBalances, $balances);
    }

    public function test_has_sufficient_credits_returns_true()
    {
        $billable = new TestBillableForCredits();
        $billable->setBalance(['usd' => -1000]); // $10 in credits

        $result = $billable->hasSufficientCredits(500, 'usd'); // Need $5

        $this->assertTrue($result);
    }

    public function test_has_sufficient_credits_returns_false()
    {
        $billable = new TestBillableForCredits();
        $billable->setBalance(['usd' => -200]); // $2 in credits

        $result = $billable->hasSufficientCredits(500, 'usd'); // Need $5

        $this->assertFalse($result);
    }

    public function test_apply_credit_to_usage_insufficient_credits()
    {
        $billable = new TestBillableForCredits();
        $billable->setBalance(['usd' => 100]); // Positive balance means debt, not credit

        $result = $billable->applyCreditToUsage(500, 'meter_123');

        $this->assertEquals([
            'applied_credits' => 0,
            'remaining_usage' => 500,
            'insufficient_credits' => true,
        ], $result);
    }
}

class TestBillableForCredits
{
    use \Laravel\Cashier\Concerns\ManagesBillingCredits;

    protected $balance = [];

    public function assertCustomerExists()
    {
        // Mock implementation
    }

    public function stripeId()
    {
        return 'cus_test123';
    }

    public function getKey()
    {
        return 'user_123';
    }

    public function asStripeCustomer()
    {
        $customer = new TestStripeCustomer();
        $customer->balance = $this->balance;

        return $customer;
    }

    public function stripe()
    {
        return new \stdClass(); // Simple mock for now
    }

    public function setBalance(array $balance)
    {
        $this->balance = $balance;
    }
}

class TestStripeCustomer
{
    public $balance = [];
}
