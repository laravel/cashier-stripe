<?php

namespace Laravel\Cashier\Concerns;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionBuilder;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription.
     *
     * @param  string  $type
     * @param  string|string[]  $prices
     * @return \Laravel\Cashier\SubscriptionBuilder
     */
    public function newSubscription(string $type, string|array $prices = []): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $type, $prices);
    }

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param  string  $type
     * @param  string|null  $price
     * @return bool
     */
    public function onTrial(string $type = 'default', ?string $price = null): bool
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Determine if the Stripe model's trial has ended.
     *
     * @param  string  $type
     * @param  string|null  $price
     * @return bool
     */
    public function hasExpiredTrial(string $type = 'default', ?string $price = null): bool
    {
        if (func_num_args() === 0 && $this->hasExpiredGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->hasExpiredTrial()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Determine if the Stripe model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter the given query for generic trials.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGenericTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the Stripe model's "generic" trial at the model level has expired.
     *
     * @return bool
     */
    public function hasExpiredGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter the given query for expired generic trials.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeHasExpiredGenericTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
    }

    /**
     * Get the ending date of the trial.
     *
     * @param  string  $type
     * @return \Illuminate\Support\Carbon|null
     */
    public function trialEndsAt(string $type = 'default')
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return $this->trial_ends_at;
        }

        if ($subscription = $this->subscription($type)) {
            return $subscription->trial_ends_at;
        }

        return $this->trial_ends_at;
    }

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string  $type
     * @param  string|null  $price
     * @return bool
     */
    public function subscribed(string $type = 'default', ?string $price = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return ! $price || $subscription->hasPrice($price);
    }

    /**
     * Get a subscription instance by $type.
     *
     * @param  string  $type
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Cashier::$subscriptionModel, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the customer's subscription has an incomplete payment.
     *
     * @param  string  $type
     * @return bool
     */
    public function hasIncompletePayment(string $type = 'default'): bool
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->hasIncompletePayment();
        }

        return false;
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given products.
     *
     * @param  string|string[]  $products
     * @param  string  $type
     * @return bool
     */
    public function subscribedToProduct(string|array $products, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

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
     * @param  string  $type
     * @return bool
     */
    public function subscribedToPrice(string|array $prices, $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

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
    public function onProduct(string $product): bool
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
    public function onPrice(string $price): bool
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
    public function taxRates(): array
    {
        return [];
    }

    /**
     * Get the tax rates to apply to individual subscription items.
     *
     * @return array
     */
    public function priceTaxRates(): array
    {
        return [];
    }
}
