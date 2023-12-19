<?php

namespace Laravel\Cashier\Concerns;

use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionBuilder;
use LogicException;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription.
     *
     * @param  string  $type
     * @param  string|string[]  $prices
     * @return \Laravel\Cashier\SubscriptionBuilder
     */
    public function newSubscription($type, $prices = [])
    {
        return new SubscriptionBuilder($this, $type, $prices);
    }

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param  string|null  $type
     * @param  string|null  $price
     * @return bool
     */
    public function onTrial($type = null, $price = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        return ! is_null($this->subscription($type, function (Subscription $subscription) use ($price) {
            if (! $subscription->onTrial()) {
                return false;
            }

            return ! $price || $subscription->hasPrice($price);
        }));
    }

    /**
     * Determine if the Stripe model's trial has ended.
     *
     * @param  string|null  $type
     * @param  string|null  $price
     * @return bool
     */
    public function hasExpiredTrial($type = null, $price = null)
    {
        if (func_num_args() === 0 && $this->hasExpiredGenericTrial()) {
            return true;
        }

        return ! is_null($this->subscription($type, function (Subscription $subscription) use ($price) {
            if (! $subscription->hasExpiredTrial()) {
                return false;
            }

            return ! $price || $subscription->hasPrice($price);
        }));
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
     * Filter the given query for generic trials.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGenericTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the Stripe model's "generic" trial at the model level has expired.
     *
     * @return bool
     */
    public function hasExpiredGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter the given query for expired generic trials.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeHasExpiredGenericTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
    }

    /**
     * Get the ending date of the trial.
     *
     * @param  string|null  $type
     * @return \Illuminate\Support\Carbon|null
     */
    public function trialEndsAt($type = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return $this->trial_ends_at;
        }

        // Require a subscription type if there are multiple subscriptions types.
        throw_if(
            is_null($type) && $this->subscriptions()->groupBy('type')->count() > 1,
            new LogicException('Please provide a subscription type when you have multiple ones.')
        );

        if ($subscription = $this->subscription($type)) {
            return $subscription->trial_ends_at;
        }

        return $this->trial_ends_at;
    }

    /**
     * Determine if the Stripe model has a valid subscription, optionally filtered by a price ID.
     *
     * @param  string|null  $type
     * @param  string|null  $price
     * @return bool
     */
    public function subscribed($type = null, $price = null)
    {
        return ! is_null($this->subscription($type, function (Subscription $subscription) use ($price) {
            if (! $subscription->valid()) {
                return false;
            }

            return ! $price || $subscription->hasPrice($price);
        }));
    }

    /**
     * Get a subscription instance optionally filtered by their $type and a callback.
     *
     * @param  string|null  $type
     * @param  callable|null  $callback
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($type = null, callable $callback = null)
    {
        /** @var \Illuminate\Database\Eloquent\Collection */
        $subscriptions = is_null($type)
            ? $this->subscriptions
            : $this->subscriptions->where('type', $type);

        return $subscriptions
            ->groupBy('type')
            ->map(fn ($results) => $results->first())
            ->first($callback);
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
     * Determine if any of the customer's subscriptions has an incomplete payment.
     *
     * @param  string|null  $type
     * @return bool
     */
    public function hasIncompletePayment($type = null)
    {
        return ! is_null($this->subscription($type, function (Subscription $subscription) {
            return $subscription->hasIncompletePayment();
        }));
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given products.
     *
     * @param  string|string[]  $products
     * @param  string|null  $type
     * @return bool
     */
    public function subscribedToProduct($products, $type = null)
    {
        return ! is_null($this->subscription($type, function (Subscription $subscription) use ($products) {
            if (! $subscription->valid()) {
                return false;
            }

            foreach ((array) $products as $product) {
                if ($subscription->hasProduct($product)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given prices.
     *
     * @param  string|string[]  $prices
     * @param  string|null  $type
     * @return bool
     */
    public function subscribedToPrice($prices, $type = null)
    {
        return ! is_null($this->subscription($type, function (Subscription $subscription) use ($prices) {
            if (! $subscription->valid()) {
                return false;
            }

            foreach ((array) $prices as $price) {
                if ($subscription->hasPrice($price)) {
                    return true;
                }
            }

            return false;
        }));
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
