<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Exceptions\InvalidCustomerBalanceTransaction;
use Stripe\CustomerBalanceTransaction as StripeCustomerBalanceTransaction;

class CustomerBalanceTransaction
{
    /**
     * Create a new CustomerBalanceTransaction instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\CustomerBalanceTransaction  $transaction
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCustomerBalanceTransaction
     */
    public function __construct(protected $owner, protected StripeCustomerBalanceTransaction $transaction)
    {
        if ($owner->stripe_id !== $transaction->customer) {
            throw InvalidCustomerBalanceTransaction::invalidOwner($transaction, $owner);
        }
    }

    /**
     * Get the total transaction amount.
     *
     * @return string
     */
    public function amount(): string
    {
        return $this->formatAmount($this->rawAmount());
    }

    /**
     * Get the raw total transaction amount.
     *
     * @return int
     */
    public function rawAmount(): int
    {
        return $this->transaction->amount;
    }

    /**
     * Get the ending balance.
     *
     * @return string
     */
    public function endingBalance(): string
    {
        return $this->formatAmount($this->rawEndingBalance());
    }

    /**
     * Get the raw ending balance.
     *
     * @return int
     */
    public function rawEndingBalance(): int
    {
        return $this->transaction->ending_balance;
    }

    /**
     * Get the balance type of the transaction.
     *
     * @return string|null
     */
    public function balanceType(): ?string
    {
        return $this->transaction->balance_type;
    }

    /**
     * Get the checkout session ID for this transaction.
     *
     * @return string|null
     */
    public function checkoutSession(): ?string
    {
        return $this->transaction->checkout_session;
    }

    /**
     * Determine if this transaction is a checkout session subscription payment.
     *
     * @return bool
     */
    public function isCheckoutSessionSubscriptionPayment(): bool
    {
        return $this->transaction->balance_type === 'checkout_session_subscription_payment';
    }

    /**
     * Determine if this transaction is a canceled checkout session subscription payment.
     *
     * @return bool
     */
    public function isCheckoutSessionSubscriptionPaymentCanceled(): bool
    {
        return $this->transaction->balance_type === 'checkout_session_subscription_payment_canceled';
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount(int $amount): string
    {
        return Cashier::formatAmount($amount, $this->transaction->currency);
    }

    /**
     * Return the related invoice for this transaction.
     *
     * @return \Laravel\Cashier\Invoice
     */
    public function invoice(): Invoice
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
    public function __get(string $key)
    {
        return $this->transaction->{$key};
    }
}
