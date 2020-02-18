<?php

namespace Laravel\Cashier\Exceptions;

use Exception;

class InvalidStripeCustomer extends Exception
{
    /**
     * Create a new InvalidStripeCustomer instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function nonCustomer($owner)
    {
        return new static(class_basename($owner).' is not a Stripe customer. See the createAsStripeCustomer method.');
    }

    /**
     * Create a new InvalidStripeCustomer instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function exists($owner)
    {
        return new static(class_basename($owner)." is already a Stripe customer with ID {$owner->stripe_id}.");
    }
}
