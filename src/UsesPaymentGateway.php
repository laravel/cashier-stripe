<?php

namespace Laravel\Cashier;

trait UsesPaymentGateway
{
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

    protected function getGateway()
    {
        return Cashier::gateway($this->getPaymentGatewayAttribute());
    }
}
