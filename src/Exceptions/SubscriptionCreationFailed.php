<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Stripe\Subscription;

class SubscriptionCreationFailed extends Exception
{
    public static function incomplete(Subscription $subscription)
    {
        return new static("The attempt to create a subscription for plan \"{$subscription->plan->nickname}\" for customer \"{$subscription->customer}\" failed because the subscription was incomplete. For more information on incomplete subscriptions, see https://stripe.com/docs/billing/lifecycle#incomplete");
    }
}
