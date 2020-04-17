<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Concerns\Prorates;
use Stripe\SubscriptionItem as StripeSubscriptionItem;

/**
 * @property \Laravel\Cashier\Subscription|null $subscription
 */
class SubscriptionItem extends Model
{
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
        $this->incrementQuantity($count);

        $this->subscription->invoice();

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
        $item = $this->swap($plan, $options);

        $this->subscription->invoice();

        return $item;
    }

    /**
     * Get the subscription as a Stripe subscription item object.
     *
     * @return StripeSubscriptionItem
     */
    public function asStripeSubscriptionItem()
    {
        return StripeSubscriptionItem::retrieve(
            $this->stripe_id,
            $this->subscription->owner->stripeOptions()
        );
    }
}
