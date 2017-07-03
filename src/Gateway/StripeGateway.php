<?php

namespace Laravel\Cashier\Gateway;

class StripeGateway extends Gateway
{
    /**
     * Get the name of this gateway.
     *
     * @return string
     */
    public function getName()
    {
        return 'stripe';
    }
}
