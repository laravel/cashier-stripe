<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Concerns\InteractsWithPaymentBehavior;
use Laravel\Cashier\Concerns\Prorates;
use Stripe\SubscriptionItem as StripeSubscriptionItem;

/**
 * @property \Laravel\Cashier\Subscription|null $subscription
 */
class SubscriptionItem extends Model
{
    use InteractsWithPaymentBehavior;
    use Prorates;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the subscription that the item belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function usageRecords()
    {
        return $this->hasMany(SubscriptionUsage::class);
    }

    /**
     * Increment the quantity of the subscription item.
     *
     * @param  int  $count
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementQuantity($count = 1)
    {
        $this->updateQuantity($this->quantity + $count);

        return $this;
    }

    /**
     *  Increment the quantity of the subscription item, and invoice immediately.
     *
     * @param  int  $count
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementAndInvoice($count = 1)
    {
        $this->alwaysInvoice();

        $this->incrementQuantity($count);

        return $this;
    }

    /**
     * Decrement the quantity of the subscription item.
     *
     * @param  int  $count
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function decrementQuantity($count = 1)
    {
        $this->updateQuantity(max(1, $this->quantity - $count));

        return $this;
    }

    /**
     * Update the quantity of the subscription item.
     *
     * @param  int  $quantity
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function updateQuantity($quantity)
    {
        $this->subscription->guardAgainstIncomplete();

        $stripeSubscriptionItem = $this->asStripeSubscriptionItem();

        $stripeSubscriptionItem->quantity = $quantity;

        $stripeSubscriptionItem->payment_behavior = $this->paymentBehavior();

        $stripeSubscriptionItem->proration_behavior = $this->prorateBehavior();

        $stripeSubscriptionItem->save();

        $this->quantity = $quantity;

        $this->save();

        if ($this->subscription->hasSinglePlan()) {
            $this->subscription->quantity = $quantity;

            $this->subscription->save();
        }

        return $this;
    }

    /**
     * Swap the subscription item to a new Stripe plan.
     *
     * @param  string  $plan
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swap($plan, $options = [])
    {
        $this->subscription->guardAgainstIncomplete();

        $options = array_merge([
            'plan' => $plan,
            'quantity' => $this->quantity,
            'payment_behavior' => $this->paymentBehavior(),
            'proration_behavior' => $this->prorateBehavior(),
            'tax_rates' => $this->subscription->getPlanTaxRatesForPayload($plan),
        ], $options);

        $item = StripeSubscriptionItem::update(
            $this->stripe_id,
            $options,
            $this->subscription->owner->stripeOptions()
        );

        $this->fill([
            'stripe_plan' => $plan,
            'quantity' => $item->quantity,
        ])->save();

        if ($this->subscription->hasSinglePlan()) {
            $this->subscription->fill([
                'stripe_plan' => $plan,
                'quantity' => $item->quantity,
            ])->save();
        }

        return $this;
    }

    /**
     * Swap the subscription item to a new Stripe plan, and invoice immediately.
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
        $this->alwaysInvoice();

        return $this->swap($plan, $options);
    }

    /**
     * Provides Stripe with usage information for a subscription item with a metered Stripe plan.
     *
     * @param  int  $quantity
     * @return $this
     */
    public function incrementUsage($quantity = 1)
    {
        $record = $this->usageRecords()->create(compact('quantity'));

        StripeSubscriptionItem::createUsageRecord($this->stripe_id, [
            'quantity' => $quantity,
            'action' => 'increment',
            'timestamp' => $record->created_at->timestamp,
        ]);

        return $this;
    }

    /**
     * Updates a usage record at a particular timestamp with a quantity.
     *
     * @param  int  $quantity
     * @param  \Carbon\Carbon|null $timestamp  Overwrites the usage quantity at a particular timestamp
     * @return $this
     */
    public function updateUsageRecord($quantity, $timestamp)
    {
        $record = $this->usageRecords()->firstOrCreate(
            ['created_at' => $timestamp],
            compact('quantity')
        );

        StripeSubscriptionItem::createUsageRecord($this->stripe_id, [
            'quantity' => $quantity,
            'action' => 'set',
            'timestamp' => $record->created_at->timestamp,
        ]);

        $record->quantity = $quantity;
        $record->save();

        return $this;
    }

    /**
     * Update the underlying Stripe subscription item information for the model.
     *
     * @param  array  $options
     * @return \Stripe\SubscriptionItem
     */
    public function updateStripeSubscriptionItem(array $options = [])
    {
        return StripeSubscriptionItem::update(
            $this->stripe_id, $options, $this->subscription->owner->stripeOptions()
        );
    }

    /**
     * Get the subscription as a Stripe subscription item object.
     *
     * @param  array  $expand
     * @return \Stripe\SubscriptionItem
     */
    public function asStripeSubscriptionItem(array $expand = [])
    {
        return StripeSubscriptionItem::retrieve(
            ['id' => $this->stripe_id, 'expand' => $expand],
            $this->subscription->owner->stripeOptions()
        );
    }
}
