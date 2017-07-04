<?php

namespace Laravel\Cashier\Gateway;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
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

    abstract public function buildSubscription(Model $billable, $subscription, $plan);

    abstract public function manageBilling(Billable $billable);

    /**
     * Get the name of the gateway.
     *
     * @return string
     */
    abstract public function getName();
}
