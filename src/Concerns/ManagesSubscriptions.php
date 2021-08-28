<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionBuilder;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription.
     *
     * @param  string  $name
     * @param  string|string[]  $prices
     * @return \Laravel\Cashier\SubscriptionBuilder
     */
    public function newSubscription($name, $prices = [])
    {
        return new SubscriptionBuilder($this, $name, $prices);
    }

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param  string  $name
     * @param  string|null  $price
     * @return bool
     */
    public function onTrial($name = 'default', $price = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($name);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Determine if the Stripe model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get the ending date of the trial.
     *
     * @param  string  $name
     * @return \Illuminate\Support\Carbon|null
     */
    public function trialEndsAt($name = 'default')
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return $this->trial_ends_at;
        }

        if ($subscription = $this->subscription($name)) {
            return $subscription->trial_ends_at;
        }

        return $this->trial_ends_at;
    }

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string  $name
     * @param  string|null  $price
     * @return bool
     */
    public function subscribed($name = 'default', $price = null)
    {
        $subscription = $this->subscription($name);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Get a subscription instance by name.
     *
     * @param  string  $name
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($name = 'default')
    {
        return $this->subscriptions->where('name', $name)->first();
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Cashier::$subscriptionModel, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the customer's subscription has an incomplete payment.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasIncompletePayment($name = 'default')
    {
        if ($subscription = $this->subscription($name)) {
            return $subscription->hasIncompletePayment();
        }

        return false;
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given products.
     *
     * @param  string|string[]  $products
     * @param  string  $name
     * @return bool
     */
    public function subscribedToProduct($products, $name = 'default')
    {
        $subscription = $this->subscription($name);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $products as $product) {
            if ($subscription->hasProduct($product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given prices.
     *
     * @param  string|string[]  $prices
     * @param  string  $name
     * @return bool
     */
    public function subscribedToPrice($prices, $name = 'default')
    {
        $subscription = $this->subscription($name);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $prices as $price) {
            if ($subscription->hasPrice($price)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the customer has a valid subscription on the given product.
     *
     * @param  string  $product
     * @return bool
     */
    public function onProduct($product)
    {
        return ! is_null($this->subscriptions->first(function (Subscription $subscription) use ($product) {
            return $subscription->valid() && $subscription->hasProduct($product);
        }));
    }

    /**
     * Determine if the customer has a valid subscription on the given price.
     *
     * @param  string  $price
     * @return bool
     */
    public function onPrice($price)
    {
        return ! is_null($this->subscriptions->first(function (Subscription $subscription) use ($price) {
            return $subscription->valid() && $subscription->hasPrice($price);
        }));
    }

    /**
     * Get the tax rates to apply to the subscription.
     *
     * @return array
     */
    public function taxRates()
    {
        return [];
    }

    /**
     * Get the tax rates to apply to individual subscription items.
     *
     * @return array
     */
    public function priceTaxRates()
    {
        return [];
    }
}
