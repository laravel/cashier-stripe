<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Concerns\ManagesBillingCredits;
use PHPUnit\Framework\TestCase;

class BillingCreditsTest extends TestCase
{
    public function test_has_sufficient_credits_with_enough_balance()
    {
        $stub = new BillingCreditsStub(-5000); // $50 in credits

        $this->assertTrue($stub->hasSufficientCredits(3000));
        $this->assertTrue($stub->hasSufficientCredits(5000));
    }

    public function test_has_sufficient_credits_with_insufficient_balance()
    {
        $stub = new BillingCreditsStub(-2000); // $20 in credits

        $this->assertFalse($stub->hasSufficientCredits(3000));
    }

    public function test_has_sufficient_credits_with_zero_balance()
    {
        $stub = new BillingCreditsStub(0);

        $this->assertFalse($stub->hasSufficientCredits(100));
    }

    public function test_has_sufficient_credits_with_positive_balance()
    {
        // Positive balance means customer OWES money, no credits
        $stub = new BillingCreditsStub(1000);

        $this->assertFalse($stub->hasSufficientCredits(100));
    }

    public function test_available_credits_with_negative_balance()
    {
        $stub = new BillingCreditsStub(-5000);

        $this->assertSame(5000, $stub->availableCredits());
    }

    public function test_available_credits_with_zero_balance()
    {
        $stub = new BillingCreditsStub(0);

        $this->assertSame(0, $stub->availableCredits());
    }

    public function test_available_credits_with_positive_balance()
    {
        $stub = new BillingCreditsStub(1000);

        $this->assertSame(0, $stub->availableCredits());
    }

    public function test_calculate_credit_application_full_coverage()
    {
        $stub = new BillingCreditsStub(-5000); // $50 in credits

        $result = $stub->calculateCreditApplication(3000); // $30 usage

        $this->assertSame(3000, $result['applied_credits']);
        $this->assertSame(0, $result['remaining_usage']);
        $this->assertSame(2000, $result['credits_after']);
    }

    public function test_calculate_credit_application_partial_coverage()
    {
        $stub = new BillingCreditsStub(-2000); // $20 in credits

        $result = $stub->calculateCreditApplication(5000); // $50 usage

        $this->assertSame(2000, $result['applied_credits']);
        $this->assertSame(3000, $result['remaining_usage']);
        $this->assertSame(0, $result['credits_after']);
    }

    public function test_calculate_credit_application_exact_coverage()
    {
        $stub = new BillingCreditsStub(-3000); // $30 in credits

        $result = $stub->calculateCreditApplication(3000); // $30 usage

        $this->assertSame(3000, $result['applied_credits']);
        $this->assertSame(0, $result['remaining_usage']);
        $this->assertSame(0, $result['credits_after']);
    }

    public function test_calculate_credit_application_no_credits()
    {
        $stub = new BillingCreditsStub(0);

        $result = $stub->calculateCreditApplication(5000);

        $this->assertSame(0, $result['applied_credits']);
        $this->assertSame(5000, $result['remaining_usage']);
        $this->assertSame(0, $result['credits_after']);
    }

    public function test_calculate_credit_application_positive_balance()
    {
        $stub = new BillingCreditsStub(1000); // owes money

        $result = $stub->calculateCreditApplication(5000);

        $this->assertSame(0, $result['applied_credits']);
        $this->assertSame(5000, $result['remaining_usage']);
        $this->assertSame(0, $result['credits_after']);
    }
}

class BillingCreditsStub
{
    use ManagesBillingCredits;

    private int $balance;

    public function __construct(int $balance)
    {
        $this->balance = $balance;
    }

    public function rawBalance(): int
    {
        return $this->balance;
    }
}
