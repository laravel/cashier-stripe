<?php

namespace Laravel\Cashier\Gateway\Stripe;

use Laravel\Cashier\Gateway\SubscriptionManager as BaseManager;
use LogicException;

class SubscriptionManager extends BaseManager
{
    /**
     * Get the subscription as a Stripe subscription object.
     *
     * @return \Stripe\Subscription
     *
     * @throws \LogicException
     */
    public function asStripeSubscription()
    {
        $subscriptions = $this->subscription->owner->asCustomer('stripe')->subscriptions;

        if (! $subscriptions) {
            throw new LogicException('The Stripe customer does not have any subscriptions.');
        }

        return $subscriptions->retrieve($this->subscription->getPaymentGatewayIdAttribute());
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @param  int  $count
     * @return \Laravel\Cashier\Subscription
     */
    public function incrementQuantity($count = 1)
    {
        $this->updateQuantity($this->subscription->quantity + $count);

        return $this->subscription;
    }

    /**
     *  Increment the quantity of the subscription, and invoice immediately.
     *
     * @param  int  $count
     * @return \Laravel\Cashier\Subscription
     */
    public function incrementAndInvoice($count = 1)
    {
        $this->incrementQuantity($count);

        $this->subscription->owner->invoice();

        return $this->subscription;
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param  int  $count
     * @return \Laravel\Cashier\Subscription
     */
    public function decrementQuantity($count = 1)
    {
        $this->updateQuantity(max(1, $this->subscription->quantity - $count));

        return $this->subscription;
    }

    /**
     * Update the quantity of the subscription.
     *
     * @param  int  $quantity
     * @param  \Stripe\Customer|null  $customer
     * @return \Laravel\Cashier\Subscription
     */
    public function updateQuantity($quantity, $customer = null)
    {
        $stripeSubscription = $this->asStripeSubscription();

        $stripeSubscription->quantity = $quantity;

        $stripeSubscription->prorate = $this->subscription->prorate;

        $stripeSubscription->save();

        $this->subscription->quantity = $quantity;

        $this->subscription->save();

        return $this->subscription;
    }

    /**
     * Swap the subscription to a new Stripe plan.
     *
     * @param  string  $plan
     * @return \Laravel\Cashier\Subscription
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function swap($plan)
    {
        $stripeSubscription = $this->asStripeSubscription();

        $stripeSubscription->plan = $plan;

        $stripeSubscription->prorate = $this->subscription->prorate;

        if (! is_null($this->billingCycleAnchor)) {
            $stripeSubscription->billingCycleAnchor = $this->billingCycleAnchor;
        }

        // If no specific trial end date has been set, the default behavior should be
        // to maintain the current trial state, whether that is "active" or to run
        // the swap out with the exact number of days left on this current plan.
        if ($this->subscription->onTrial()) {
            $stripeSubscription->trial_end = $this->subscription->trial_ends_at->getTimestamp();
        } else {
            $stripeSubscription->trial_end = 'now';
        }

        // Again, if no explicit quantity was set, the default behaviors should be to
        // maintain the current quantity onto the new plan. This is a sensible one
        // that should be the expected behavior for most developers with Stripe.
        if ($this->subscription->quantity) {
            $stripeSubscription->quantity = $this->subscription->quantity;
        }

        $stripeSubscription->save();

        $this->subscription->owner->invoice();

        $this->subscription->fill([
            'stripe_plan' => $plan,
            'ends_at' => null,
        ])->save();

        return $this->subscription;
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return \Laravel\Cashier\Subscription
     * @throws \LogicException
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException;
     */
    public function resume()
    {
        if (! $this->subscription->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        $subscription = $this->asStripeSubscription();

        // To resume the subscription we need to set the plan parameter on the Stripe
        // subscription object. This will force Stripe to resume this subscription
        // where we left off. Then, we'll set the proper trial ending timestamp.
        $subscription->plan = $this->subscription->getPaymentGatewayPlanAttribute(); // FIXME

        if ($this->subscription->onTrial()) {
            $subscription->trial_end = $this->subscription->trial_ends_at->getTimestamp();
        } else {
            $subscription->trial_end = 'now';
        }

        $subscription->save();

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "cancelled". Then we will save this record in the database.
        $this->subscription->fill(['ends_at' => null])->save();

        return $this->subscription;
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return \Laravel\Cashier\Subscription
     * @throws \LogicException
     */
    public function cancel()
    {
        $subscription = $this->asStripeSubscription();

        $subscription->cancel(['at_period_end' => true]);

        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        if ($this->subscription->onTrial()) {
            $this->subscription->ends_at = $this->subscription->trial_ends_at;
        } else {
            $this->subscription->ends_at = Carbon::createFromTimestamp(
                $subscription->current_period_end
            );
        }

        $this->subscription->save();

        return $this->subscription;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return \Laravel\Cashier\Subscription
     * @throws \LogicException
     */
    public function cancelNow()
    {
        $subscription = $this->asStripeSubscription();

        $subscription->cancel();

        $this->subscription->markAsCancelled();

        return $this->subscription;
    }
}
