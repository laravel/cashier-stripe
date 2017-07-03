<?php

namespace Laravel\Cashier\Gateway;

use Laravel\Cashier\Gateway\Stripe\SubscriptionManager;
use Laravel\Cashier\Subscription;

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

    public function manageSubscription(Subscription $subscription)
    {
        return new SubscriptionManager($subscription);
    }
}
