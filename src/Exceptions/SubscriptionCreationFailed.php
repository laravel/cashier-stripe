<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Stripe\Subscription;

class SubscriptionCreationFailed extends Exception
{
    public static function cardError(Subscription $subscription)
    {
        return new static("The attempt to create a subscription for plan \"{$subscription->plan->nickname}\" for customer \"{$subscription->customer}\" failed because there was a card error.");
    }
}
