<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Gateway\Stripe\Gateway as StripeGateway;

trait BillableCompat
{
    /**
     * Set the Stripe API key.
     *
     * @param  string $key
     * @return void
     */
    public static function setStripeKey($key)
    {
        StripeGateway::setApiKey($key);
    }

    /**
     * Get the Stripe API key.
     *
     * @return string
     */
    public static function getStripeKey()
    {
        return StripeGateway::getApiKey();
    }

    /**
     * Determine if the entity has a Stripe customer ID.
     *
     * @return bool
     */
    public function hasStripeId()
    {
        return ! is_null($this->stripe_id);
    }

    /**
     * Determine if the entity has a Braintree customer ID.
     *
     * @return bool
     */
    public function hasBraintreeId()
    {
        return ! is_null($this->braintree_id);
    }
}
