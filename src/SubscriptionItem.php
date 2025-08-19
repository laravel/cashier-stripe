<?php

namespace Laravel\Cashier;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
        'meter_id' => 'string',
        'meter_event_name' => 'string',
    ];

    /**
     * Get the subscription that the item belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription(): BelongsTo
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
    public function incrementQuantity(int $count = 1)
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
    public function incrementAndInvoice(int $count = 1)
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
    public function decrementQuantity(int $count = 1)
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
    public function updateQuantity(int $quantity)
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
    public function swap(string $price, array $options = [])
    {
        $this->subscription->guardAgainstIncomplete();

        $stripePrice = $this->subscription->owner->stripe()->prices->retrieve($price);

        $meterId = null;
        $meterEventName = null;

        if (isset($stripePrice->recurring->meter)) {
            $meterId = $stripePrice->recurring->meter;
            $meter = $this->subscription->owner->stripe()->billing->meters->retrieve($meterId);
            $meterEventName = $meter->event_name;
        }

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
            'meter_id' => $meterId,
            'quantity' => $stripeSubscriptionItem->quantity,
            'meter_event_name' => $meterEventName,
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
    public function swapAndInvoice(string $price, array $options = [])
    {
        $this->alwaysInvoice();

        return $this->swap($price, $options);
    }

    /**
     * Report usage for a metered product.
     *
     * @param  int  $quantity
     * @param  \DateTimeInterface|int|null  $timestamp
     * @return \Stripe\V2\Billing\MeterEvent
     */
    public function reportUsage(int $quantity = 1, DateTimeInterface|int|null $timestamp = null)
    {
        $eventName = $this->meter_event_name;
        $meterId = $this->meter_id;

        if (! $eventName) {
            if (! $meterId) {
                // Get the price to determine the meter...
                $stripePrice = $this->subscription->owner->stripe()->prices->retrieve($this->stripe_price);

                if (! isset($stripePrice->recurring->meter)) {
                    throw new \InvalidArgumentException('Price must have a meter to report usage. Legacy usage records are no longer supported.');
                }

                $meterId = $stripePrice->recurring->meter;
            }

            // Get the meter to get the event name...
            $meter = $this->subscription->owner->stripe()->billing->meters->retrieve($meterId);

            $eventName = $meter->event_name;

            $this->forceFill(['meter_id' => $meterId, 'meter_event_name' => $eventName])->save();
        }

        // Convert timestamp to RFC 3339 format for v2 API...
        if ($timestamp instanceof DateTimeInterface) {
            $rfc3339Timestamp = $timestamp->format('c');
        } elseif (is_int($timestamp)) {
            $rfc3339Timestamp = (new \DateTime('@'.$timestamp))->format('c');
        } else {
            $rfc3339Timestamp = (new \DateTime())->format('c');
        }

        return $this->subscription->owner->stripe()->v2->billing->meterEvents->create([
            'event_name' => $eventName,
            'payload' => [
                'stripe_customer_id' => $this->subscription->owner->stripeId(),
                'value' => (string) $quantity,
            ],
            'timestamp' => $rfc3339Timestamp,
            'identifier' => Str::uuid()->toString(),
        ]);
    }

    /**
     * Get the usage records for a metered product.
     *
     * @param  array  $options
     * @return \Illuminate\Support\Collection
     */
    public function usageRecords(array $options = []): Collection
    {
        $meterId = $this->meter_id;

        if (! $meterId) {
            // Get the price to determine the meter...
            $stripePrice = $this->subscription->owner->stripe()->prices->retrieve($this->stripe_price);

            if (! isset($stripePrice->recurring->meter)) {
                throw new \InvalidArgumentException('Price must have a meter to get usage records. Legacy usage records are no longer supported.');
            }

            $meterId = $stripePrice->recurring->meter;

            $this->forceFill(['meter_id' => $meterId])->save();
        }

        // Default time range - current billing period...
        $defaultOptions = [
            'start_time' => $this->currentPeriodStart()?->getTimestamp() ?? 1,
            'end_time' => time(),
            'customer' => $this->subscription->owner->stripeId(),
        ];

        return new Collection($this->subscription->owner->stripe()->billing->meters->allEventSummaries(
            $meterId,
            array_merge($defaultOptions, $options)
        )->data);
    }

    /**
     * Get the current period start date for this subscription item.
     *
     * @param  string|null  $timezone
     * @return \Illuminate\Support\Carbon|null
     */
    public function currentPeriodStart(?string $timezone = null)
    {
        $stripeItem = $this->asStripeSubscriptionItem();

        if (! isset($stripeItem->current_period_start)) {
            return null;
        }

        $date = $this->asDateTime($stripeItem->current_period_start);

        return $timezone ? $date->setTimezone($timezone) : $date;
    }

    /**
     * Get the current period end date for this subscription item.
     *
     * @param  string|null  $timezone
     * @return \Illuminate\Support\Carbon|null
     */
    public function currentPeriodEnd(?string $timezone = null)
    {
        $stripeItem = $this->asStripeSubscriptionItem();

        if (! isset($stripeItem->current_period_end)) {
            return null;
        }

        $date = $this->asDateTime($stripeItem->current_period_end);

        return $timezone ? $date->setTimezone($timezone) : $date;
    }

    /**
     * Determine if the subscription item is currently within its trial period.
     *
     * @return bool
     */
    public function onTrial(): bool
    {
        return $this->subscription->onTrial();
    }

    /**
     * Determine if the subscription item is on a grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod(): bool
    {
        return $this->subscription->onGracePeriod();
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
