<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Stripe\CustomerBalanceTransaction as StripeCustomerBalanceTransaction;

class InvalidCustomerBalanceTransaction extends Exception
{
    /**
     * Create a new CustomerBalanceTransaction instance.
     *
     * @param  \Stripe\CustomerBalanceTransaction  $transaction
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function invalidOwner(StripeCustomerBalanceTransaction $transaction, $owner)
    {
        return new static("The transaction `{$transaction->id}` does not belong to customer `$owner->stripe_id`.");
    }
}
