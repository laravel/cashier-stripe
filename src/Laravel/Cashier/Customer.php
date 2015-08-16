<?php

namespace Laravel\Cashier;

use Stripe\Customer as StripeCustomer;

class Customer extends StripeCustomer
{
    /**
     * The subscription being managed by Cashier.
     *
     * @var \Stripe\Subscription
     */
    public $subscription;

    /**
     * Get the current subscription ID.
     *
     * @return string|null
     */
    public function getStripeSubscription()
    {
        return $this->subscription ? $this->subscription->id : null;
    }

    /**
     * Find a subscription by ID.
     *
     * @param  string  $id
     * @return \Stripe\Subscription|null
     */
    public function findSubscription($id)
    {
        foreach ($this->subscriptions->all()->data as $subscription) {
            if ($subscription->id == $id) {
                return $subscription;
            }
        }
    }

    /**
     * Create the current subscription with the given data.
     *
     * @param  array  $params
     * @return void
     */
    protected function createSubscription(array $params)
    {
        return $this->subscription = $this->subscriptions->create($params);
    }

    /**
     * Update the current subscription with the given data.
     *
     * @param  array  $params
     * @return \Stripe\Subscription
     */
    public function updateSubscription($params = null)
    {
        if (is_null($this->subscription)) {
            return $this->createSubscription($params);
        } else {
            return $this->saveSubscription($params);
        }
    }

    /**
     * Save the current subscription with the given parameters.
     *
     * @param  array  $params
     * @return \Stripe\Subscription
     */
    protected function saveSubscription($params)
    {
        foreach ($params as $key => $value) {
            $this->subscription->{$key} = $value;
        }

        $this->subscription->save();

        return $this->subscription;
    }

    /**
     * Cancel the current subscription.
     *
     * @param  array  $params
     * @return void
     */
    public function cancelSubscription($params = null)
    {
        return $this->subscription->cancel($params);
    }
}
