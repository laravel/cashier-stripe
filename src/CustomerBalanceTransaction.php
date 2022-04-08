<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Exceptions\InvalidCustomerBalanceTransaction;
use Stripe\CustomerBalanceTransaction as StripeCustomerBalanceTransaction;

class CustomerBalanceTransaction
{
    /**
     * The Stripe model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Stripe CustomerBalanceTransaction instance.
     *
     * @var \Stripe\CustomerBalanceTransaction
     */
    protected $transaction;

    /**
     * Create a new CustomerBalanceTransaction instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\CustomerBalanceTransaction  $transaction
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCustomerBalanceTransaction
     */
    public function __construct($owner, StripeCustomerBalanceTransaction $transaction)
    {
        if ($owner->stripe_id !== $transaction->customer) {
            throw InvalidCustomerBalanceTransaction::invalidOwner($transaction, $owner);
        }

        $this->owner = $owner;
        $this->transaction = $transaction;
    }

    /**
     * Get the total transaction amount.
     *
     * @return string
     */
    public function amount()
    {
        return $this->formatAmount($this->rawAmount());
    }

    /**
     * Get the raw total transaction amount.
     *
     * @return int
     */
    public function rawAmount()
    {
        return $this->transaction->amount;
    }

    /**
     * Get the ending balance.
     *
     * @return string
     */
    public function endingBalance()
    {
        return $this->formatAmount($this->rawEndingBalance());
    }

    /**
     * Get the raw ending balance.
     *
     * @return int
     */
    public function rawEndingBalance()
    {
        return $this->transaction->ending_balance;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->transaction->currency);
    }

    /**
     * Return the related invoice for this transaction.
     *
     * @return \Laravel\Cashier\Invoice
     */
    public function invoice()
    {
        return $this->transaction->invoice
            ? $this->owner->findInvoice($this->transaction->invoice)
            : null;
    }

    /**
     * Get the Stripe CustomerBalanceTransaction instance.
     *
     * @return \Stripe\CustomerBalanceTransaction
     */
    public function asStripeCustomerBalanceTransaction()
    {
        return $this->transaction;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripeCustomerBalanceTransaction()->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Stripe object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->transaction->{$key};
    }
}
