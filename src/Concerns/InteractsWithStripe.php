<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

trait InteractsWithStripe
{
    /**
     * Get the Stripe SDK client.
     *
     * @param  array  $options
     * @return \Stripe\StripeClient
     */
    public static function stripe(array $options = []): StripeClient
    {
        return Cashier::stripe($options);
    }
}
