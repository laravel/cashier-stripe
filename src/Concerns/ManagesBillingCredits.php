<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\CustomerBalanceTransaction;

trait ManagesBillingCredits
{
    use InteractsWithStripe;

    /**
     * Add billing credits to the customer's account.
     *
     * @param  int  $amount
     * @param  string  $currency
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Laravel\Cashier\CustomerBalanceTransaction
     */
    public function addBillingCredits(int $amount, string $currency = 'usd', array $options = [], array $requestOptions = []): CustomerBalanceTransaction
    {
        $this->assertCustomerExists();

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be a positive integer.');
        }

        $options = array_merge([
            'amount' => -$amount, // Negative amount for credits
            'currency' => $currency,
        ], $options);

        $stripeTransaction = $this->stripe()->customers->createBalanceTransaction(
            $this->stripeId(),
            $options,
            $requestOptions
        );

        return new CustomerBalanceTransaction($this, $stripeTransaction);
    }

    /**
     * Deduct billing credits from the customer's account.
     *
     * @param  int  $amount
     * @param  string  $currency
     * @param  array  $options
     * @param  array  $requestOptions
     * @return \Laravel\Cashier\CustomerBalanceTransaction
     */
    public function deductBillingCredits(int $amount, string $currency = 'usd', array $options = [], array $requestOptions = []): CustomerBalanceTransaction
    {
        $this->assertCustomerExists();

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Deduction amount must be a positive integer.');
        }

        $options = array_merge([
            'amount' => $amount, // Positive amount for deductions
            'currency' => $currency,
        ], $options);

        $stripeTransaction = $this->stripe()->customers->createBalanceTransaction(
            $this->stripeId(),
            $options,
            $requestOptions
        );

        return new CustomerBalanceTransaction($this, $stripeTransaction);
    }

    /**
     * Get the billing credit balance for a specific currency.
     *
     * @param  string  $currency
     * @return int
     */
    public function billingCreditBalance(string $currency = 'usd'): int
    {
        $customer = $this->asStripeCustomer();

        return $customer->balance[$currency] ?? 0;
    }

    /**
     * Get all billing credit balances.
     *
     * @return array
     */
    public function allBillingCreditBalances(): array
    {
        $customer = $this->asStripeCustomer();

        return $customer->balance ?? [];
    }

    /**
     * Check if the customer has sufficient credits for a given amount.
     *
     * @param  int  $amount
     * @param  string  $currency
     * @return bool
     */
    public function hasSufficientCredits(int $amount, string $currency = 'usd'): bool
    {
        $balance = $this->billingCreditBalance($currency);

        // Negative balance means credits, so we check if absolute value is >= amount
        return $balance < 0 && abs($balance) >= $amount;
    }

    /**
     * Apply credits to usage and return the result.
     *
     * @param  int  $usageAmount
     * @param  string|null  $meterId
     * @param  string  $currency
     * @return array
     */
    public function applyCreditToUsage(int $usageAmount, ?string $meterId = null, string $currency = 'usd'): array
    {
        $currentBalance = $this->billingCreditBalance($currency);

        // If no credits available (positive or zero balance)
        if ($currentBalance >= 0) {
            return [
                'applied_credits' => 0,
                'remaining_usage' => $usageAmount,
                'insufficient_credits' => true,
            ];
        }

        $availableCredits = abs($currentBalance);

        if ($availableCredits >= $usageAmount) {
            // Full coverage - deduct the usage amount
            $this->deductBillingCredits($usageAmount, $currency);

            return [
                'applied_credits' => $usageAmount,
                'remaining_usage' => 0,
                'credit_balance_after' => $this->billingCreditBalance($currency),
            ];
        } else {
            // Partial coverage - use all available credits
            $this->deductBillingCredits($availableCredits, $currency);

            return [
                'applied_credits' => $availableCredits,
                'remaining_usage' => $usageAmount - $availableCredits,
                'credit_balance_after' => $this->billingCreditBalance($currency),
            ];
        }
    }

    /**
     * Get a forecast of credit usage over a period.
     *
     * @param  string  $meterId
     * @param  int  $forecastDays
     * @param  string  $currency
     * @return array
     */
    public function getCreditUsageForecast(string $meterId, int $forecastDays = 30, string $currency = 'usd'): array
    {
        $currentBalance = abs($this->billingCreditBalance($currency));

        // Get usage history for analysis (last 30 days)
        $usageSummaries = $this->meterEventSummaries($meterId, time() - (30 * 24 * 60 * 60), time());
        $totalUsage = $usageSummaries->sum('aggregated_value');
        $dailyAverageUsage = $totalUsage / 30;

        $forecastedUsage = $dailyAverageUsage * $forecastDays;
        $daysUntilDepleted = $dailyAverageUsage > 0 ? $currentBalance / $dailyAverageUsage : 0;
        $sufficientForPeriod = $currentBalance >= $forecastedUsage;
        $recommendedTopUp = $sufficientForPeriod ? 0 : max(0, $forecastedUsage - $currentBalance);

        return [
            'current_balance' => $currentBalance,
            'daily_average_usage' => $dailyAverageUsage,
            'forecasted_usage' => $forecastedUsage,
            'days_until_depleted' => $daysUntilDepleted,
            'sufficient_for_period' => $sufficientForPeriod,
            'recommended_top_up' => $recommendedTopUp,
        ];
    }
}
