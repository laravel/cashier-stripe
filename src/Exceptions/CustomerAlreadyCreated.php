<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class CustomerAlreadyCreated extends Exception
{
    /**
     * Create a new CustomerAlreadyCreated instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function exists(Model $owner)
    {
        return new static(class_basename($owner)." is already a Stripe customer with ID {$owner->stripe_id}.");
    }
}
