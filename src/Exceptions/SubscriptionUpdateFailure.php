<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Laravel\Cashier\Subscription;

class SubscriptionUpdateFailure extends Exception
{
    /**
     * Create a new SubscriptionUpdateFailure instance.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @return static
     */
    public static function incompleteSubscription(Subscription $subscription)
    {
        return new static(
            "The subscription \"{$subscription->stripe_id}\" cannot be updated because its payment is incomplete."
        );
    }

    /**
     * Create a new SubscriptionUpdateFailure instance.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @param  string  $price
     * @return static
     */
    public static function duplicatePlan(Subscription $subscription, $price)
    {
        return new static(
            "The price \"$price\" is already attached to subscription \"{$subscription->stripe_id}\"."
        );
    }

    /**
     * Create a new SubscriptionUpdateFailure instance.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @return static
     */
    public static function cannotDeleteLastPlan(Subscription $subscription)
    {
        return new static(
            "The price on subscription \"{$subscription->stripe_id}\" cannot be removed because it is the last one."
        );
    }
}
