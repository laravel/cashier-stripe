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
     * Whether to use gateway attributes like stripe_id or standard attributes like payment_gateway_id
     *
     * @var bool
     */
    protected $namespacedGatewayAttributes = false;

    /**
     * Get the explicitly assigned gateway.
     *
     * @return null|string
     */
    public function getAssignedPaymentGateway()
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

        return null;
    }

    /**
     * Accessor for 'payment_gateway'
     *
     * @return string
     */
    public function getPaymentGatewayAttribute()
    {
        if ($gateway = $this->getAssignedPaymentGateway()) {
            return $gateway;
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

    public function setPaymentGatewayId($id, $gateway = null)
    {
        $gateway = $gateway ?: $this->payment_gateway;

        if ($this->namespacedGatewayAttributes) {
            $this->attributes["{$gateway}_id"] = $id;
            return;
        }

        $this->attributes['payment_gateway'] = $gateway;
        $this->attributes['payment_gateway_id'] = $id;

    }

    public function setPaymentGatewayIdAttribute($id)
    {
        return $this->setPaymentGatewayId($id);
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

    public function setPaymentGatewayPlan($plan, $gateway = null)
    {
        $gateway = $gateway ?: $this->payment_gateway;

        if ($this->namespacedGatewayAttributes) {
            $this->attributes["{$gateway}_plan"] = $plan;
            return;
        }

        $this->attributes['payment_gateway'] = $gateway;
        $this->attributes['payment_gateway_plan'] = $plan;

    }

    public function setPaymentGatewayPlanAttribute($plan)
    {
        return $this->setPaymentGatewayPlan($plan);
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
}
