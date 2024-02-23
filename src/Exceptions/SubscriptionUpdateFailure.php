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
            "The subscription \"{$subscription->stripeId()}\" cannot be updated because its payment is incomplete."
        );
    }

    /**
     * Create a new SubscriptionUpdateFailure instance.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @param  string  $price
     * @return static
     */
    public static function duplicatePrice(Subscription $subscription, $price)
    {
        return new static(
            "The price \"$price\" is already attached to subscription \"{$subscription->stripeId()}\"."
        );
    }

    /**
     * Create a new SubscriptionUpdateFailure instance.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @return static
     */
    public static function cannotDeleteLastPrice(Subscription $subscription)
    {
        return new static(
            "The price on subscription \"{$subscription->stripeId()}\" cannot be removed because it is the last one."
        );
    }
}
