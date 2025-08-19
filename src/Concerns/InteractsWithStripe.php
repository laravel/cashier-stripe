<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Cashier;

trait InteractsWithStripe
{
    /**
     * Get the Stripe SDK client.
     *
     * @param  array  $options
     * @return \Stripe\StripeClient
     */
    public static function stripe(array $options = [])
    {
        return Cashier::stripe($options);
    }
}
