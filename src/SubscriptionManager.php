<?php

namespace Laravel\Cashier;

abstract class SubscriptionManager
{
    protected $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}
