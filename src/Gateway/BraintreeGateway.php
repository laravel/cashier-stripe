<?php

namespace Laravel\Cashier\Gateway;

use Braintree\Plan as BraintreePlan;
use Laravel\Cashier\Gateway\Braintree\Exception;
use Laravel\Cashier\Gateway\Braintree\SubscriptionManager;
use Laravel\Cashier\Subscription;

class BraintreeGateway extends Gateway
{
    /**
     * Get the Braintree plan that has the given ID.
     *
     * @param  string  $id
     * @return \Braintree\Plan
     * @throws \Laravel\Cashier\Gateway\Braintree\Exception
     */
    public static function findPlan($id)
    {
        $plans = BraintreePlan::all();

        foreach ($plans as $plan) {
            if ($plan->id === $id) {
                return $plan;
            }
        }

        throw new Exception("Unable to find Braintree plan with ID [{$id}].");
    }

    /**
     * Get the name of this gateway.
     *
     * @return string
     */
    public function getName()
    {
        return 'braintree';
    }

    /**
     * Convert value as cents into dollars.
     *
     * @param  int  $value
     * @return float
     */
    public function convertZeroDecimalValue($value)
    {
        return (float) $value / 100;
    }

    public function manageSubscription(Subscription $subscription)
    {
        return new SubscriptionManager($subscription);
    }
}
