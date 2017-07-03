<?php

namespace Laravel\Cashier\Gateway;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

abstract class Gateway
{
    /**
     * Register gateway with Cashier.
     */
    public function register()
    {
        Cashier::addGateway($this);
    }

    /**
     * Convert a zero-decimal value (eg. cents) into the value appropriate for this gateway.
     *
     * @param  int  $value
     * @return int|float
     */
    public function convertZeroDecimalValue($value)
    {
        return $value;
    }

    abstract public function manageSubscription(Subscription $subscription);

    /**
     * Get the name of the gateway.
     *
     * @return string
     */
    abstract public function getName();
}
