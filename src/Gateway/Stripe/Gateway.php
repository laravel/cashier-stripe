<?php

namespace Laravel\Cashier\Gateway\Stripe;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Gateway\Gateway as BaseGateway;
use Laravel\Cashier\Subscription;

class Gateway extends BaseGateway
{
    /**
     * Stripe API key.
     *
     * @var string
     */
    protected static $apiKey;

    /**
     * Get the Stripe API key.
     *
     * @return string
     */
    public static function getApiKey()
    {
        return static::$apiKey;
    }

    /**
     * Set the Stripe API key.
     *
     * @param  string $key
     * @return void
     */
    public static function setApiKey($key)
    {
        static::$apiKey = $key;
    }

    /**
     * Get the name of this gateway.
     *
     * @return string
     */
    public function getName()
    {
        return 'stripe';
    }

    /**
     * Manage a subscription.
     *
     * @param \Laravel\Cashier\Subscription $subscription
     * @return \Laravel\Cashier\Gateway\Stripe\SubscriptionManager
     */
    public function manageSubscription(Subscription $subscription)
    {
        return new SubscriptionManager($subscription);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $billable
     * @param $subscription
     * @param $plan
     * @return \Laravel\Cashier\Gateway\Stripe\SubscriptionBuilder
     */
    public function buildSubscription(Model $billable, $subscription, $plan)
    {
        return new SubscriptionBuilder($billable, $subscription, $plan);
    }

    /**
     * @param \Laravel\Cashier\Billable $billable
     * @return \Laravel\Cashier\Gateway\Stripe\BillingManager
     */
    public function manageBilling(Billable $billable)
    {
        return new BillingManager($billable, $this);
    }
}
