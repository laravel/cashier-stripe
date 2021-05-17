<?php

namespace Laravel\Cashier;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Cashier\Concerns\InteractsWithPaymentBehavior;
use Laravel\Cashier\Concerns\Prorates;
use Laravel\Cashier\Database\Factories\SubscriptionItemFactory;
use Stripe\SubscriptionItem as StripeSubscriptionItem;

/**
 * @property \Laravel\Cashier\Subscription|null $subscription
 */
class SubscriptionItem extends Model
{
    use HasFactory;
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
        return $this->belongsTo(Cashier::$subscriptionModel);
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

        $stripeSubscriptionItem = $this->updateStripeSubscriptionItem([
            'payment_behavior' => $this->paymentBehavior(),
            'proration_behavior' => $this->prorateBehavior(),
            'quantity' => $quantity,
            'expand' => ['subscription.latest_invoice.payment_intent'],
        ]);

        $this->fill([
            'quantity' => $quantity,
        ])->save();

        if ($this->subscription->hasSinglePlan()) {
            $this->subscription->fill([
                'stripe_status' => $stripeSubscriptionItem->subscription->status,
                'quantity' => $quantity,
            ])->save();
        }

        if ($this->subscription->hasIncompletePayment()) {
            (new Payment(
                $stripeSubscriptionItem->subscription->latest_invoice->payment_intent
            ))->validate();
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
    public function swap($plan, array $options = [])
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
    public function swapAndInvoice($plan, array $options = [])
    {
        $this->alwaysInvoice();

        return $this->swap($plan, $options);
    }

    /**
     * Report usage for a metered product.
     *
     * @param  int  $quantity
     * @param  \DateTimeInterface|int|null  $timestamp
     * @return \Stripe\UsageRecord
     */
    public function reportUsage($quantity = 1, $timestamp = null)
    {
        $timestamp = $timestamp instanceof DateTimeInterface ? $timestamp->getTimestamp() : $timestamp;

        return StripeSubscriptionItem::createUsageRecord($this->stripe_id, [
            'quantity' => $quantity,
            'action' => $timestamp ? 'set' : 'increment',
            'timestamp' => $timestamp ?? time(),
        ], $this->subscription->owner->stripeOptions());
    }

    /**
     * Get the usage records for a metered product.
     *
     * @param  array  $options
     * @return \Illuminate\Support\Collection
     */
    public function usageRecords($options = [])
    {
        return new Collection(StripeSubscriptionItem::allUsageRecordSummaries(
            $this->stripe_id, $options, $this->subscription->owner->stripeOptions()
        )->data);
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

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public static function newFactory()
    {
        return SubscriptionItemFactory::new();
    }
}
