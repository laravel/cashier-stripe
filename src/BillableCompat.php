<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Gateway\Stripe\Gateway as StripeGateway;

/**
 * Trait BillableCompat
 *
 * @package Laravel\Cashier
 * @mixin \Laravel\Cashier\Billable
 */
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
     * Create a Stripe customer for the given Stripe model.
     *
     * @param  string  $token
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function createAsStripeCustomer($token, array $options = [])
    {
        return $this->createAsCustomer('stripe', $token, $options);
    }

    /**
     * Get the Stripe customer for the Stripe model.
     *
     * @return \Stripe\Customer
     */
    public function asStripeCustomer()
    {
        return $this->asCustomer('stripe');
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

    /**
     * Create a Braintree customer for the given model.
     *
     * @param  string  $token
     * @param  array  $options
     * @return \Braintree\Customer
     * @throws \Exception
     */
    public function createAsBraintreeCustomer($token, array $options = [])
    {
        return $this->createAsCustomer('braintree', $token, $options);
    }

    /**
     * Get the Braintree customer for the model.
     *
     * @return \Braintree\Customer
     */
    public function asBraintreeCustomer()
    {
        return $this->asCustomer('braintree');
    }
}
