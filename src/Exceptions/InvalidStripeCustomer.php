<?php

namespace Laravel\Cashier\Exceptions;

use Exception;

class InvalidStripeCustomer extends Exception
{
    /**
     * Create a new CustomerFailure instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return self
     */
    public static function nonCustomer($owner)
    {
        return new static(class_basename($owner).' is not a Stripe customer. See the createAsStripeCustomer method.');
    }
}
