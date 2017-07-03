<?php

namespace Laravel\Cashier\Gateway\Braintree;

use Braintree\Subscription as BraintreeSubscription;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;
use Laravel\Cashier\SubscriptionManager as BaseManager;
use LogicException;

class SubscriptionManager extends BaseManager
{
    /**
     * Swap the subscription to a new Braintree plan.
     *
     * @param  string $plan
     * @return \Laravel\Cashier\Subscription
     * @throws \Exception
     */
    public function swap($plan)
    {
        if ($this->subscription->onGracePeriod() && $this->subscription->braintree_plan === $plan) {
            return $this->subscription->resume();
        }

        if (! $this->subscription->active()) {
            return $this->subscription->owner->newSubscription($this->subscription->name, $plan)->skipTrial()->create();
        }

        $plan = BraintreeService::findPlan($plan);

        if ($this->subscription->wouldChangeBillingFrequency($plan) && $this->subscription->prorate) {
            return $this->subscription->swapAcrossFrequencies($plan);
        }

        $subscription = $this->asBraintreeSubscription();

        $response = BraintreeSubscription::update($subscription->id, [
            'planId' => $plan->id,
            'price' => (string) round($plan->price * (1 + ($this->subscription->owner->taxPercentage() / 100)), 2),
            'neverExpires' => true,
            'numberOfBillingCycles' => null,
            'options' => [
                'prorateCharges' => $this->subscription->prorate,
            ],
        ]);

        if ($response->success) {
            $this->subscription->fill([
                'braintree_plan' => $plan->id,
                'ends_at' => null,
            ])->save();
        } else {
            throw new Exception('Braintree failed to swap plans: '.$response->message);
        }

        return $this->subscription;
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return \Laravel\Cashier\Subscription
     *
     * @throws \LogicException
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function resume()
    {
        if (! $this->subscription->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        $subscription = $this->asBraintreeSubscription();

        BraintreeSubscription::update($subscription->id, [
            'neverExpires' => true,
            'numberOfBillingCycles' => null,
        ]);

        $this->subscription->fill(['ends_at' => null])->save();

        return $this->subscription;
    }

    /**
     * Get the subscription as a Braintree subscription object.
     *
     * @return \Braintree\Subscription
     */
    public function asBraintreeSubscription()
    {
        return BraintreeSubscription::find($this->subscription->braintree_id);
    }

    /**
     * Determine if the given plan would alter the billing frequency.
     *
     * @param  string $plan
     * @return bool
     */
    protected function wouldChangeBillingFrequency($plan)
    {
        return $plan->billingFrequency !== BraintreeService::findPlan($this->subscription->braintree_plan)->billingFrequency;
    }

    /**
     * Swap the subscription to a new Braintree plan with a different frequency.
     *
     * @param  string $plan
     * @return \Laravel\Cashier\Subscription
     */
    protected function swapAcrossFrequencies($plan)
    {
        $currentPlan = BraintreeService::findPlan($this->subscription->braintree_plan);

        $discount = $this->subscription->switchingToMonthlyPlan($currentPlan, $plan) ? $this->subscription->getDiscountForSwitchToMonthly($currentPlan, $plan) : $this->subscription->getDiscountForSwitchToYearly();

        $options = [];

        if ($discount->amount > 0 && $discount->numberOfBillingCycles > 0) {
            $options = [
                'discounts' => [
                    'add' => [
                        [
                            'inheritedFromId' => 'plan-credit',
                            'amount' => (float) $discount->amount,
                            'numberOfBillingCycles' => $discount->numberOfBillingCycles,
                        ],
                    ],
                ],
            ];
        }

        $this->subscription->cancelNow();

        return $this->subscription->owner->newSubscription($this->subscription->name, $plan->id)->skipTrial()->create(null, [], $options);
    }

    /**
     * Determine if the user is switching form yearly to monthly billing.
     *
     * @param  \Braintree\Plan $currentPlan
     * @param  \Braintree\Plan $plan
     * @return bool
     */
    protected function switchingToMonthlyPlan($currentPlan, $plan)
    {
        return $currentPlan->billingFrequency == 12 && $plan->billingFrequency == 1;
    }

    /**
     * Get the discount to apply when switching to a monthly plan.
     *
     * @param  \Braintree\Plan $currentPlan
     * @param  \Braintree\Plan $plan
     * @return object
     */
    protected function getDiscountForSwitchToMonthly($currentPlan, $plan)
    {
        return (object) [
            'amount' => $plan->price,
            'numberOfBillingCycles' => floor($this->subscription->moneyRemainingOnYearlyPlan($currentPlan) / $plan->price),
        ];
    }

    /*
     * Apply a coupon to the subscription.
     *
     * @param  string  $coupon
     * @param  bool  $removeOthers
     * @return void
     */

    /**
     * Calculate the amount of discount to apply to a swap to monthly billing.
     *
     * @param  \Braintree\Plan $plan
     * @return float
     */
    protected function moneyRemainingOnYearlyPlan($plan)
    {
        return ($plan->price / 365) * Carbon::today()->diffInDays(Carbon::instance($this->asBraintreeSubscription()->billingPeriodEndDate), false);
    }

    /**
     * Get the discount to apply when switching to a yearly plan.
     *
     * @return object
     */
    protected function getDiscountForSwitchToYearly()
    {
        $amount = 0;

        foreach ($this->asBraintreeSubscription()->discounts as $discount) {
            if ($discount->id == 'plan-credit') {
                $amount += (float) $discount->amount * $discount->numberOfBillingCycles;
            }
        }

        return (object) [
            'amount' => $amount,
            'numberOfBillingCycles' => 1,
        ];
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return \Laravel\Cashier\Subscription
     */
    public function cancelNow()
    {
        $subscription = $this->asBraintreeSubscription();

        BraintreeSubscription::cancel($subscription->id);

        $this->subscription->markAsCancelled();

        return $this->subscription;
    }

    public function applyCoupon($coupon, $removeOthers = false)
    {
        if (! $this->subscription->active()) {
            throw new InvalidArgumentException("Unable to apply coupon. Subscription not active.");
        }

        BraintreeSubscription::update($this->subscription->braintree_id, [
            'discounts' => [
                'add' => [
                    [
                        'inheritedFromId' => $coupon,
                    ],
                ],
                'remove' => $removeOthers ? $this->subscription->currentDiscounts() : [],
            ],
        ]);
    }

    /**
     * Get the current discounts for the subscription.
     *
     * @return array
     */
    protected function currentDiscounts()
    {
        return collect($this->asBraintreeSubscription()->discounts)->map(function ($discount) {
            return $discount->id;
        })->all();
    }

    /**
     * Cancel the subscription.
     *
     * @return \Laravel\Cashier\Subscription
     */
    public function cancel()
    {
        $subscription = $this->asBraintreeSubscription();

        if ($this->subscription->onTrial()) {
            BraintreeSubscription::cancel($subscription->id);

            $this->subscription->markAsCancelled();
        } else {
            BraintreeSubscription::update($subscription->id, [
                'numberOfBillingCycles' => $subscription->currentBillingCycle,
            ]);

            $this->subscription->ends_at = $subscription->billingPeriodEndDate;

            $this->subscription->save();
        }

        return $this->subscription;
    }
}
