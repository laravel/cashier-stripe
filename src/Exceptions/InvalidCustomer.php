<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidCustomer extends Exception
{
    /**
     * Create a new InvalidCustomer instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function notYetCreated(Model $owner)
    {
        return new static(class_basename($owner).' is not a Stripe customer yet. See the createAsStripeCustomer method.');
    }
}
