<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Stripe\Subscription;

class SubscriptionCreationIncomplete extends Exception
{
    public static function requiresAction(Subscription $subscription)
    {
        return new static("The attempt to create a subscription for plan \"{$subscription->plan->nickname}\" for customer \"{$subscription->customer}\" is incomplete and requires extra action.");
    }
}
