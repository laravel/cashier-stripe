<?php

namespace Laravel\Cashier\Concerns;

trait ManagesBillingCredits
{
    /**
     * Determine if the customer has sufficient credit balance for a given amount.
     *
     * Customer balance is negative when they have credits (Stripe convention),
     * so we check that the absolute value of the negative balance is >= the amount.
     *
     * @param  int  $amount
     * @return bool
     */
    public function hasSufficientCredits(int $amount): bool
    {
        $balance = $this->rawBalance();

        return $balance < 0 && abs($balance) >= $amount;
    }

    /**
     * Get the customer's available credit balance as a positive integer.
     *
     * Returns 0 if the customer has no credits (positive or zero balance).
     *
     * @return int
     */
    public function availableCredits(): int
    {
        $balance = $this->rawBalance();

        return $balance < 0 ? abs($balance) : 0;
    }

    /**
     * Apply credits toward a usage amount and return the result.
     *
     * This is a calculation-only helper. It does NOT modify the customer's
     * balance on Stripe. Use creditBalance()/debitBalance() for actual
     * balance modifications.
     *
     * @param  int  $usageAmount  The usage amount to cover with credits (in cents).
     * @return array{applied_credits: int, remaining_usage: int, credits_after: int}
     */
    public function calculateCreditApplication(int $usageAmount): array
    {
        $available = $this->availableCredits();

        if ($available <= 0) {
            return [
                'applied_credits' => 0,
                'remaining_usage' => $usageAmount,
                'credits_after' => 0,
            ];
        }

        $applied = min($available, $usageAmount);

        return [
            'applied_credits' => $applied,
            'remaining_usage' => $usageAmount - $applied,
            'credits_after' => $available - $applied,
        ];
    }

    /**
     * Add billing credits to the customer's account.
     *
     * This is a convenience alias for creditBalance() with a clearer name
     * for usage-based billing contexts.
     *
     * @param  int  $amount  Positive amount in cents to credit.
     * @param  string|null  $description
     * @param  array  $options
     * @return \Laravel\Cashier\CustomerBalanceTransaction
     */
    public function addBillingCredits(int $amount, ?string $description = null, array $options = [])
    {
        return $this->creditBalance($amount, $description, $options);
    }

    /**
     * Deduct billing credits from the customer's account.
     *
     * This is a convenience alias for debitBalance() with a clearer name
     * for usage-based billing contexts.
     *
     * @param  int  $amount  Positive amount in cents to debit.
     * @param  string|null  $description
     * @param  array  $options
     * @return \Laravel\Cashier\CustomerBalanceTransaction
     */
    public function deductBillingCredits(int $amount, ?string $description = null, array $options = [])
    {
        return $this->debitBalance($amount, $description, $options);
    }
}
