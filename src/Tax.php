<?php

namespace Laravel\Cashier;

use Stripe\TaxRate as StripeTaxRate;

class Tax
{
    /**
     * Create a new Tax instance.
     *
     * @return void
     */
    public function __construct(
        protected int $amount,
        protected string $currency,
        protected ?StripeTaxRate $taxRate = null
    ) {
        //
    }

    /**
     * Get the applied currency.
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Get the total tax that was paid (or will be paid).
     */
    public function amount(): string
    {
        return $this->formatAmount($this->amount);
    }

    /**
     * Get the raw total tax that was paid (or will be paid).
     */
    public function rawAmount(): int
    {
        return $this->amount;
    }

    /**
     * Format the given amount into a displayable currency.
     */
    protected function formatAmount(int $amount): string
    {
        return Cashier::formatAmount($amount, $this->currency);
    }

    /**
     * Determine if the tax is inclusive or not.
     */
    public function isInclusive(): bool
    {
        return $this->taxRate instanceof StripeTaxRate
            ? $this->taxRate->inclusive
            : false;
    }

    /**
     * Get the Stripe TaxRate object.
     */
    public function taxRate(): ?StripeTaxRate
    {
        return $this->taxRate;
    }

    /**
     * Dynamically get values from the Stripe object.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->taxRate instanceof StripeTaxRate && in_array($key, $this->taxRate->keys())
            ? $this->taxRate->{$key}
            : null;
    }
}
