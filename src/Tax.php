<?php

namespace Laravel\Cashier;

use Stripe\TaxRate as StripeTaxRate;

class Tax
{
    /**
     * Create a new Tax instance.
     *
     * @param  int  $amount
     * @param  string  $currency
     * @param  \Stripe\TaxRate|null  $taxRate
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
     *
     * @return string
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Get the total tax that was paid (or will be paid).
     *
     * @return string
     */
    public function amount(): string
    {
        return $this->formatAmount($this->amount);
    }

    /**
     * Get the raw total tax that was paid (or will be paid).
     *
     * @return int
     */
    public function rawAmount(): int
    {
        return $this->amount;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount(int $amount): string
    {
        return Cashier::formatAmount($amount, $this->currency);
    }

    /**
     * Determine if the tax is inclusive or not.
     *
     * @return bool
     */
    public function isInclusive(): bool
    {
        return $this->taxRate instanceof StripeTaxRate
            ? $this->taxRate->inclusive
            : false;
    }

    /**
     * Get the Stripe TaxRate object.
     *
     * @return \Stripe\TaxRate|null
     */
    public function taxRate(): ?StripeTaxRate
    {
        return $this->taxRate;
    }

    /**
     * Dynamically get values from the Stripe object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->taxRate instanceof StripeTaxRate && in_array($key, $this->taxRate->keys())
            ? $this->taxRate->{$key}
            : null;
    }
}
