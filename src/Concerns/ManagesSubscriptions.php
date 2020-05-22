<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionBuilder;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription.
     *
     * @param  string  $name
     * @param  string|string[]  $plans
     * @return \Laravel\Cashier\SubscriptionBuilder
     */
    public function newSubscription($name, $plans)
    {
        return new SubscriptionBuilder($this, $name, $plans);
    }

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param  string  $name
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial($name = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($name);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return $plan ? $subscription->hasPlan($plan) : true;
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
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string  $name
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed($name = 'default', $plan = null)
    {
        $subscription = $this->subscription($name);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $plan ? $subscription->hasPlan($plan) : true;
    }

    /**
     * Get a subscription instance by name.
     *
     * @param  string  $name
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($name = 'default')
    {
        return $this->subscriptions->sortByDesc(function (Subscription $subscription) {
            return $subscription->created_at->getTimestamp();
        })->first(function (Subscription $subscription) use ($name) {
            return $subscription->name === $name;
        });
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, $this->getForeignKey())->orderBy('created_at', 'desc');
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
     * Determine if the Stripe model is actively subscribed to one of the given plans.
     *
     * @param  string|string[]  $plans
     * @param  string  $name
     * @return bool
     */
    public function subscribedToPlan($plans, $name = 'default')
    {
        $subscription = $this->subscription($name);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $plans as $plan) {
            if ($subscription->hasPlan($plan)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the entity has a valid subscription on the given plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function onPlan($plan)
    {
        return ! is_null($this->subscriptions->first(function (Subscription $subscription) use ($plan) {
            return $subscription->valid() && $subscription->hasPlan($plan);
        }));
    }

    /**
     * Get the tax percentage to apply to the subscription.
     *
     * @return int|float
     * @deprecated Please migrate to the new Tax Rates API.
     */
    public function taxPercentage()
    {
        return 0;
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
    public function planTaxRates()
    {
        return [];
    }
}
