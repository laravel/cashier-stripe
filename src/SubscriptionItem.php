<?php

namespace Laravel\Cashier;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Cashier\Concerns\HandlesPaymentFailures;
use Laravel\Cashier\Concerns\InteractsWithPaymentBehavior;
use Laravel\Cashier\Concerns\Prorates;
use Laravel\Cashier\Database\Factories\SubscriptionItemFactory;

/**
 * @property \Laravel\Cashier\Subscription|null $subscription
 */
class SubscriptionItem extends Model
{
    use HandlesPaymentFailures;
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
        $model = Cashier::$subscriptionModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
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
        ]);

        $this->fill([
            'quantity' => $stripeSubscriptionItem->quantity,
        ])->save();

        $stripeSubscription = $this->subscription->asStripeSubscription();

        if ($this->subscription->hasSinglePrice()) {
            $this->subscription->fill([
                'quantity' => $stripeSubscriptionItem->quantity,
            ]);
        }

        $this->subscription->fill([
            'stripe_status' => $stripeSubscription->status,
        ])->save();

        $this->handlePaymentFailure($this->subscription);

        return $this;
    }

    /**
     * Swap the subscription item to a new Stripe price.
     *
     * @param  string  $price
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swap($price, array $options = [])
    {
        $this->subscription->guardAgainstIncomplete();

        $stripeSubscriptionItem = $this->updateStripeSubscriptionItem(array_merge(
            array_filter([
                'price' => $price,
                'quantity' => $this->quantity,
                'payment_behavior' => $this->paymentBehavior(),
                'proration_behavior' => $this->prorateBehavior(),
                'tax_rates' => $this->subscription->getPriceTaxRatesForPayload($price),
            ], function ($value) {
                return ! is_null($value);
            }),
            $options));

        $this->fill([
            'stripe_product' => $stripeSubscriptionItem->price->product,
            'stripe_price' => $stripeSubscriptionItem->price->id,
            'quantity' => $stripeSubscriptionItem->quantity,
        ])->save();

        $stripeSubscription = $this->subscription->asStripeSubscription();

        if ($this->subscription->hasSinglePrice()) {
            $this->subscription->fill([
                'stripe_price' => $price,
                'quantity' => $stripeSubscriptionItem->quantity,
            ]);
        }

        $this->subscription->fill([
            'stripe_status' => $stripeSubscription->status,
        ])->save();

        $this->handlePaymentFailure($this->subscription);

        return $this;
    }

    /**
     * Swap the subscription item to a new Stripe price, and invoice immediately.
     *
     * @param  string  $price
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swapAndInvoice($price, array $options = [])
    {
        $this->alwaysInvoice();

        return $this->swap($price, $options);
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

        return $this->subscription->owner->stripe()->subscriptionItems->createUsageRecord($this->stripe_id, [
            'quantity' => $quantity,
            'action' => $timestamp ? 'set' : 'increment',
            'timestamp' => $timestamp ?? time(),
        ]);
    }

    /**
     * Get the usage records for a metered product.
     *
     * @param  array  $options
     * @return \Illuminate\Support\Collection
     */
    public function usageRecords($options = [])
    {
        return new Collection($this->subscription->owner->stripe()->subscriptionItems->allUsageRecordSummaries(
            $this->stripe_id, $options
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
        return $this->subscription->owner->stripe()->subscriptionItems->update(
            $this->stripe_id, $options
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
        return $this->subscription->owner->stripe()->subscriptionItems->retrieve(
            $this->stripe_id, ['expand' => $expand]
        );
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return SubscriptionItemFactory::new();
    }
}
