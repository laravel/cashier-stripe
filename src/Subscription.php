<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use LogicException;
use Stripe\Subscription as StripeSubscription;

class Subscription extends Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['items'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at', 'ends_at',
        'created_at', 'updated_at',
    ];

    /**
     * Indicates if the plan change should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var string|null
     */
    protected $billingCycleAnchor = null;

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        $model = config('cashier.model');

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription items related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * Determine if the subscription has multiple plans.
     *
     * @return bool
     */
    public function hasMultiplePlans()
    {
        return is_null($this->stripe_plan);
    }

    /**
     * Determine if the subscription has a single plan.
     *
     * @return bool
     */
    public function hasSinglePlan()
    {
        return ! $this->hasMultiplePlans();
    }

    /**
     * Determine if the subscription has a specific plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function hasPlan($plan)
    {
        if ($this->hasMultiplePlans()) {
            return $this->items->contains(function (SubscriptionItem $item) use ($plan) {
                return $item->stripe_plan === $plan;
            });
        }

        return $this->stripe_plan === $plan;
    }

    /**
     * Get the subscription item for the given plan.
     *
     * @param  string  $plan
     * @return \Laravel\Cashier\SubscriptionItem
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findItemOrFail($plan)
    {
        return $this->items()->where('stripe_plan', $plan)->firstOrFail();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is incomplete.
     *
     * @return bool
     */
    public function incomplete()
    {
        return $this->stripe_status === StripeSubscription::STATUS_INCOMPLETE;
    }

    /**
     * Filter query by incomplete.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeIncomplete($query)
    {
        $query->where('stripe_status', StripeSubscription::STATUS_INCOMPLETE);
    }

    /**
     * Determine if the subscription is past due.
     *
     * @return bool
     */
    public function pastDue()
    {
        return $this->stripe_status === StripeSubscription::STATUS_PAST_DUE;
    }

    /**
     * Filter query by past due.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopePastDue($query)
    {
        $query->where('stripe_status', StripeSubscription::STATUS_PAST_DUE);
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return (is_null($this->ends_at) || $this->onGracePeriod()) &&
            $this->stripe_status !== StripeSubscription::STATUS_INCOMPLETE &&
            $this->stripe_status !== StripeSubscription::STATUS_INCOMPLETE_EXPIRED &&
            (! Cashier::$deactivatePastDue || $this->stripe_status !== StripeSubscription::STATUS_PAST_DUE) &&
            $this->stripe_status !== StripeSubscription::STATUS_UNPAID;
    }

    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere(function ($query) {
                    $query->onGracePeriod();
                });
        })->where('stripe_status', '!=', StripeSubscription::STATUS_INCOMPLETE)
            ->where('stripe_status', '!=', StripeSubscription::STATUS_INCOMPLETE_EXPIRED)
            ->where('stripe_status', '!=', StripeSubscription::STATUS_UNPAID);

        if (Cashier::$deactivatePastDue) {
            $query->where('stripe_status', '!=', StripeSubscription::STATUS_PAST_DUE);
        }
    }

    /**
     * Sync the Stripe status of the subscription.
     *
     * @return void
     */
    public function syncStripeStatus()
    {
        $subscription = $this->asStripeSubscription();

        $this->stripe_status = $subscription->status;

        $this->save();
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     *
     * @return bool
     */
    public function recurring()
    {
        return ! $this->onTrial() && ! $this->cancelled();
    }

    /**
     * Filter query by recurring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeRecurring($query)
    {
        $query->notOnTrial()->notCancelled();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Filter query by cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeCancelled($query)
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Filter query by not cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotCancelled($query)
    {
        $query->whereNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return $this->cancelled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeEnded($query)
    {
        $query->cancelled()->notOnGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnTrial($query)
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGracePeriod($query)
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnGracePeriod($query)
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @param  int  $count
     * @param  string|null  $plan
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementQuantity($count = 1, $plan = null)
    {
        $this->updateQuantity($this->quantity + $count, $plan = null);

        return $this;
    }

    /**
     *  Increment the quantity of the subscription, and invoice immediately.
     *
     * @param  int  $count
     * @param  string|null  $plan
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementAndInvoice($count = 1, $plan = null)
    {
        $this->incrementQuantity($count, $plan);

        $this->invoice();

        return $this;
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param  int  $count
     * @param  string|null  $plan
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function decrementQuantity($count = 1, $plan = null)
    {
        $this->updateQuantity(max(1, $this->quantity - $count), $plan);

        return $this;
    }

    /**
     * Update the quantity of the subscription.
     *
     * @param  int  $quantity
     * @param  string|null  $plan
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function updateQuantity($quantity, $plan = null)
    {
        if ($this->incomplete()) {
            throw SubscriptionUpdateFailure::incompleteSubscription($this);
        }

        $this->guardAgainstMultiplePlans($plan);

        $item = null;

        if ($this->hasSinglePlan()) {
            $stripeSubscription = $this->asStripeSubscription();

            $stripeSubscription->quantity = $quantity;

            $stripeSubscription->proration_behavior = $this->prorateBehavior();

            $stripeSubscription->save();

            $this->quantity = $quantity;

            $this->save();

            $item = $this->items()->first();
        } elseif ($plan) {
            $item = $this->findItemOrFail($plan);
        }

        if ($item) {
            $stripeSubscriptionItem = $item->asStripeSubscriptionItem();

            $stripeSubscriptionItem->quantity = $quantity;

            $stripeSubscriptionItem->proration_behavior = $this->prorateBehavior();

            $stripeSubscriptionItem->save();

            $item->quantity = $quantity;

            $item->save();
        }

        return $this;
    }

    /**
     * Indicate that the plan change should not be prorated.
     *
     * @return $this
     */
    public function noProrate()
    {
        $this->prorate = false;

        return $this;
    }

    /**
     * Indicate that the plan change should be prorated.
     *
     * @return $this
     */
    public function prorate()
    {
        $this->prorate = true;

        return $this;
    }

    /**
     * Determine the prorating behavior when updating the subscription.
     *
     * @return string
     */
    public function prorateBehavior()
    {
        return $this->prorate ? 'create_prorations' : 'none';
    }

    /**
     * Change the billing cycle anchor on a plan change.
     *
     * @param  \DateTimeInterface|int|string  $date
     * @return $this
     */
    public function anchorBillingCycleOn($date = 'now')
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        $this->billingCycleAnchor = $date;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;

        return $this;
    }

    /**
     * Extend an existing subscription's trial period.
     *
     * @param  \Carbon\CarbonInterface  $date
     * @return $this
     */
    public function extendTrial(CarbonInterface $date)
    {
        if (! $date->isFuture()) {
            throw new InvalidArgumentException("Extending a subscription's trial requires a date in the future.");
        }

        $subscription = $this->asStripeSubscription();

        $subscription->trial_end = $date->getTimestamp();

        $subscription->save();

        $this->trial_ends_at = $date;

        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to a new Stripe plan.
     *
     * @param  string  $plan
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swap($plan, $options = [])
    {
        if ($this->hasMultiplePlans()) {
            throw SubscriptionUpdateFailure::swapOnMultiplan($this);
        }

        if ($this->incomplete()) {
            throw SubscriptionUpdateFailure::incompleteSubscription($this);
        }

        $subscription = $this->asStripeSubscription();

        $subscription->plan = $plan;
        $subscription->proration_behavior = $this->prorateBehavior();
        $subscription->cancel_at_period_end = false;

        if (! is_null($this->billingCycleAnchor)) {
            $subscription->billing_cycle_anchor = $this->billingCycleAnchor;
        }

        foreach ($options as $key => $option) {
            $subscription->$key = $option;
        }

        // If no specific trial end date has been set, the default behavior should be
        // to maintain the current trial state, whether that is "active" or to run
        // the swap out with the exact number of days left on this current plan.
        if ($this->onTrial()) {
            $subscription->trial_end = $this->trial_ends_at->getTimestamp();
        } else {
            $subscription->trial_end = 'now';
        }

        // Again, if no explicit quantity was set, the default behaviors should be to
        // maintain the current quantity onto the new plan. This is a sensible one
        // that should be the expected behavior for most developers with Stripe.
        if ($this->quantity) {
            $subscription->quantity = $this->quantity;
        }

        $subscription->save();

        $this->fill([
            'stripe_plan' => $plan,
            'ends_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Swap the subscription to a new Stripe plan, and invoice immediately.
     *
     * @param  string  $plan
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swapAndInvoice($plan, $options = [])
    {
        $subscription = $this->swap($plan, $options);

        $this->invoice();

        return $subscription;
    }

    /**
     * Add a new Stripe plan to the subscription.
     *
     * @param  string  $plan
     * @param  int  $quantity
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addPlan($plan, $quantity = 1, $options = [])
    {
        if ($this->incomplete()) {
            throw SubscriptionUpdateFailure::incompleteSubscription($this);
        }

        if ($this->items->contains('stripe_plan', $plan)) {
            throw SubscriptionUpdateFailure::duplicatePlan($this, $plan);
        }

        $subscription = $this->asStripeSubscription();

        $item = $subscription->items->create(array_merge([
            'plan' => $plan,
            'quantity' => $quantity,
            'tax_rates' => $this->getPlanTaxRatesForPayload($plan),
            'proration_behavior' => $this->prorateBehavior(),
        ], $options));

        $this->items()->create([
            'stripe_id' => $item->id,
            'stripe_plan' => $plan,
            'quantity' => $quantity,
        ]);

        $this->unsetRelation('items');

        if ($this->items()->count() > 1) {
            $this->fill([
                'stripe_plan' => null,
                'quantity' => null,
            ])->save();
        }

        return $this;
    }

    /**
     * Add a new Stripe plan to the subscription, and invoice immediately.
     *
     * @param  string  $plan
     * @param  int  $quantity
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addPlanAndInvoice($plan, $quantity = 1, $options = [])
    {
        $subscription = $this->addPlan($plan, $quantity, $options);

        $this->invoice();

        return $subscription;
    }

    /**
     * Remove a Stripe plan from the subscription.
     *
     * @param  string  $plan
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function removePlan($plan)
    {
        $item = $this->findItemOrFail($plan);

        if ($this->hasSinglePlan()) {
            throw SubscriptionUpdateFailure::cannotDeleteLastPlan($this);
        }

        $item->asStripeSubscriptionItem()->delete([
            'proration_behavior' => $this->prorateBehavior(),
        ]);

        $this->items()->where('stripe_plan', $plan)->delete();

        $this->unsetRelation('items');

        if ($this->items()->count() === 1) {
            $item = $this->items()->first();

            $this->fill([
                'stripe_plan' => $item->stripe_plan,
                'quantity' => $item->quantity,
            ])->save();
        }

        return $this;
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        $subscription = $this->asStripeSubscription();

        $subscription->cancel_at_period_end = true;

        $subscription = $subscription->save();

        $this->stripe_status = $subscription->status;

        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = Carbon::createFromTimestamp(
                $subscription->current_period_end
            );
        }

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $subscription = $this->asStripeSubscription();

        $subscription->cancel();

        $this->markAsCancelled();

        return $this;
    }

    /**
     * Mark the subscription as cancelled.
     *
     * @return void
     * @internal
     */
    public function markAsCancelled()
    {
        $this->fill([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function resume()
    {
        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        $subscription = $this->asStripeSubscription();

        $subscription->cancel_at_period_end = false;

        // To resume the subscription we need to set the plan parameter on the Stripe
        // subscription object. This will force Stripe to resume this subscription
        // where we left off. Then, we'll set the proper trial ending timestamp.
        $subscription->plan = $this->stripe_plan;

        if ($this->onTrial()) {
            $subscription->trial_end = $this->trial_ends_at->getTimestamp();
        } else {
            $subscription->trial_end = 'now';
        }

        $subscription = $subscription->save();

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "cancelled". Then we will save this record in the database.
        $this->fill([
            'stripe_status' => $subscription->status,
            'ends_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Invoice the subscription outside of the regular billing cycle.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|bool
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoice(array $options = [])
    {
        try {
            return $this->user->invoice(array_merge($options, ['subscription' => $this->stripe_id]));
        } catch (IncompletePayment $exception) {
            // Set the new Stripe subscription status immediately when payment fails...
            $this->fill([
                'stripe_status' => $exception->payment->invoice->subscription->status,
            ])->save();

            throw $exception;
        }
    }

    /**
     * Sync the tax rates of the user to the subscription.
     *
     * @return void
     */
    public function syncTaxRates()
    {
        $stripeSubscription = $this->asStripeSubscription();

        $stripeSubscription->default_tax_rates = $this->user->taxRates();

        $stripeSubscription->save();

        foreach ($this->items as $item) {
            $stripeSubscriptionItem = $item->asStripeSubscriptionItem();

            $stripeSubscriptionItem->tax_rates = $this->user->planTaxRates($item->stripe_plan);

            $stripeSubscriptionItem->save();
        }
    }

    /**
     * Get the plan tax rates for the Stripe payload.
     *
     * @param  string  $plan
     * @return array|null
     */
    protected function getPlanTaxRatesForPayload($plan)
    {
        if ($taxRates = $this->owner->planTaxRates()) {
            return $taxRates[$plan] ?? null;
        }
    }

    /**
     * Determine if the subscription has an incomplete payment.
     *
     * @return bool
     */
    public function hasIncompletePayment()
    {
        return $this->pastDue() || $this->incomplete();
    }

    /**
     * Get the latest payment for a Subscription.
     *
     * @return \Laravel\Cashier\Payment|null
     */
    public function latestPayment()
    {
        $paymentIntent = $this->asStripeSubscription(['latest_invoice.payment_intent'])
            ->latest_invoice
            ->payment_intent;

        return $paymentIntent
            ? new Payment($paymentIntent)
            : null;
    }

    /**
     * Make sure a plan argument is provided when the subscription is a multi plan subscription.
     *
     * @param  string|null  $plan
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function guardAgainstMultiplePlans($plan)
    {
        if (is_null($plan) && $this->hasMultiplePlans()) {
            throw new InvalidArgumentException(
                'This method requires a plan argument since the subscription has multiple plans.'
            );
        }
    }

    /**
     * Get the subscription as a Stripe subscription object.
     *
     * @param  array  $expand
     * @return \Stripe\Subscription
     */
    public function asStripeSubscription(array $expand = [])
    {
        return StripeSubscription::retrieve(
            ['id' => $this->stripe_id, 'expand' => $expand],
            $this->owner->stripeOptions()
        );
    }
}
