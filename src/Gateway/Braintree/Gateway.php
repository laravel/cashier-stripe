<?php

namespace Laravel\Cashier\Gateway\Braintree;

use Braintree\Customer as BraintreeCustomer;
use Braintree\PaymentMethod;
use Braintree\PayPalAccount;
use Braintree\Plan as BraintreePlan;
use Braintree\Subscription as BraintreeSubscription;
use Braintree\Transaction as BraintreeTransaction;
use Braintree\TransactionSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Gateway\Carbon;
use Laravel\Cashier\Gateway\Gateway as BaseGateway;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Gateway extends BaseGateway
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

    public function buildSubscription(Model $billable, $subscription, $plan)
    {
        return new SubscriptionBuilder($billable, $subscription, $plan);
    }

    /**
     * @param \Laravel\Cashier\Billable $billable
     * @return \Laravel\Cashier\Gateway\Braintree\BillingManager
     */
    public function manageBilling(Billable $billable)
    {
        return new BillingManager($billable, $this);
    }
}
