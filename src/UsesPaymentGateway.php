<?php

namespace Laravel\Cashier;

/**
 * Trait UsesPaymentGateway
 *
 * @package Laravel\Cashier
 * @property-read string $payment_gateway
 * @property-read string $payment_gateway_id
 */
trait UsesPaymentGateway
{
    /**
     * Accessor for 'payment_gateway'
     *
     * @return string
     */
    public function getPaymentGatewayAttribute()
    {
        if ($gateway = $this->getAttribute('payment_gateway')) {
            return $gateway;
        }

        if ($this->getAttribute('stripe_id')) {
            return 'stripe';
        }

        if ($this->getAttribute('braintree_id')) {
            return 'braintree';
        }

        return Cashier::getDefaultGateway();
    }

    /**
     * Accessor for 'payment_gateway_id'
     *
     * @return string
     */
    public function getPaymentGatewayIdAttribute()
    {
        if ($stripe_id = $this->getAttribute('stripe_id')) {
            return $stripe_id;
        }

        if ($braintree_id = $this->getAttribute('braintree_id')) {
            return $braintree_id;
        }

        return $this->getAttribute('payment_gateway_id');
    }

    /**
     * Accessor for 'payment_gateway_plan'
     *
     * @return string
     */
    public function getPaymentGatewayPlanAttribute()
    {
        if ($stripe_plan = $this->getAttribute('stripe_plan')) {
            return $stripe_plan;
        }

        if ($braintree_plan = $this->getAttribute('braintree_plan')) {
            return $braintree_plan;
        }

        return $this->getAttribute('payment_gateway_plan');
    }

    /**
     * Get the gateway for this model.
     *
     * @return \Laravel\Cashier\Gateway\Gateway
     */
    protected function getGateway()
    {
        return Cashier::gateway($this->payment_gateway);
    }

    /**
     * Only run code for specific gateway.
     *
     * @param  string  $gateway
     * @param  \Closure  $callback
     * @return $this
     *
     * @throws \Laravel\Cashier\Exception
     */
    protected function forGateway($gateway, \Closure $callback)
    {
        if ($gateway === $this->getPaymentGatewayPlanAttribute()) {
            $callback(Cashier::gateway($gateway)); // FIXME
        }

        return $this;
    }
}
