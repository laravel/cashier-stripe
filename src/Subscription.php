<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\AllowsCoupons;
use Laravel\Cashier\Concerns\HandlesPaymentFailures;
use Laravel\Cashier\Concerns\InteractsWithPaymentBehavior;
use Laravel\Cashier\Concerns\Prorates;
use Laravel\Cashier\Database\Factories\SubscriptionFactory;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Exceptions\InvalidCoupon;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use LogicException;
use Stripe\Subscription as StripeSubscription;

/**
 * @property \Laravel\Cashier\Billable&\Illuminate\Database\Eloquent\Model $owner
 */
class Subscription extends Model
{
    use AllowsCoupons;
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
        'ends_at' => 'datetime',
        'quantity' => 'integer',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var string|null
     */
    protected ?string $billingCycleAnchor = null;

    /**
     * The billing thresholds for the subscription.
     *
     * @var array|null
     */
    protected ?array $billingThresholds = null;

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner(): BelongsTo
    {
        $model = Cashier::$customerModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription items related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(Cashier::$subscriptionItemModel);
    }

    /**
     * Determine if the subscription has multiple prices.
     *
     * @return bool
     */
    public function hasMultiplePrices(): bool
    {
        return is_null($this->stripe_price);
    }

    /**
     * Determine if the subscription has a single price.
     *
     * @return bool
     */
    public function hasSinglePrice(): bool
    {
        return ! $this->hasMultiplePrices();
    }

    /**
     * Determine if the subscription has a specific product.
     *
     * @param  string  $product
     * @return bool
     */
    public function hasProduct(string $product): bool
    {
        return $this->items->contains(function (SubscriptionItem $item) use ($product) {
            return $item->stripe_product === $product;
        });
    }

    /**
     * Determine if the subscription has a specific price.
     *
     * @param  string  $price
     * @return bool
     */
    public function hasPrice(string $price): bool
    {
        if ($this->hasMultiplePrices()) {
            return $this->items->contains(function (SubscriptionItem $item) use ($price) {
                return $item->stripe_price === $price;
            });
        }

        return $this->stripe_price === $price;
    }

    /**
     * Get the subscription item for the given price.
     *
     * @param  string  $price
     * @return \Laravel\Cashier\SubscriptionItem
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findItemOrFail(string $price): SubscriptionItem
    {
        return $this->items()->where('stripe_price', $price)->firstOrFail();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is incomplete.
     *
     * @return bool
     */
    public function incomplete(): bool
    {
        return $this->stripe_status === StripeSubscription::STATUS_INCOMPLETE;
    }

    /**
     * Filter query by incomplete.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeIncomplete(Builder $query): void
    {
        $query->where('stripe_status', StripeSubscription::STATUS_INCOMPLETE);
    }

    /**
     * Determine if the subscription is past due.
     *
     * @return bool
     */
    public function pastDue(): bool
    {
        return $this->stripe_status === StripeSubscription::STATUS_PAST_DUE;
    }

    /**
     * Filter query by past due.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopePastDue(Builder $query): void
    {
        $query->where('stripe_status', StripeSubscription::STATUS_PAST_DUE);
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active(): bool
    {
        return ! $this->ended() &&
            (! Cashier::$deactivateIncomplete || $this->stripe_status !== StripeSubscription::STATUS_INCOMPLETE) &&
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
    public function scopeActive(Builder $query): void
    {
        $query->where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere(function ($query) {
                    $query->onGracePeriod();
                });
        })->where('stripe_status', '!=', StripeSubscription::STATUS_INCOMPLETE_EXPIRED)
            ->where('stripe_status', '!=', StripeSubscription::STATUS_UNPAID);

        if (Cashier::$deactivatePastDue) {
            $query->where('stripe_status', '!=', StripeSubscription::STATUS_PAST_DUE);
        }

        if (Cashier::$deactivateIncomplete) {
            $query->where('stripe_status', '!=', StripeSubscription::STATUS_INCOMPLETE);
        }
    }

    /**
     * Sync the Stripe status of the subscription.
     *
     * @return void
     */
    public function syncStripeStatus(): void
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
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Filter query by recurring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeRecurring(Builder $query): void
    {
        $query->notOnTrial()->notCanceled();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function canceled(): bool
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Filter query by canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeCanceled(Builder $query): void
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Filter query by not canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotCanceled(Builder $query): void
    {
        $query->whereNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeEnded(Builder $query): void
    {
        $query->canceled()->notOnGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the subscription's trial has expired.
     *
     * @return bool
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter query by expired trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeExpiredTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
    }

    /**
     * Filter query by not on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnTrial(Builder $query): void
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGracePeriod(Builder $query): void
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnGracePeriod(Builder $query): void
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @param  int  $count
     * @param  string|null  $price
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementQuantity(int $count = 1, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)
                ->setPaymentBehavior($this->paymentBehavior)
                ->setProrationBehavior($this->prorationBehavior)
                ->incrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        return $this->updateQuantity($this->quantity + $count, $price);
    }

    /**
     *  Increment the quantity of the subscription, and invoice immediately.
     *
     * @param  int  $count
     * @param  string|null  $price
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementAndInvoice(int $count = 1, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        $this->alwaysInvoice();

        return $this->incrementQuantity($count, $price);
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param  int  $count
     * @param  string|null  $price
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function decrementQuantity(int $count = 1, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)
                ->setPaymentBehavior($this->paymentBehavior)
                ->setProrationBehavior($this->prorationBehavior)
                ->decrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        return $this->updateQuantity(max(1, $this->quantity - $count), $price);
    }

    /**
     * Update the quantity of the subscription.
     *
     * @param  int  $quantity
     * @param  string|null  $price
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function updateQuantity(int $quantity, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)
                ->setPaymentBehavior($this->paymentBehavior)
                ->setProrationBehavior($this->prorationBehavior)
                ->updateQuantity($quantity);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        $stripeSubscription = $this->updateStripeSubscription([
            'payment_behavior' => $this->paymentBehavior(),
            'proration_behavior' => $this->prorateBehavior(),
            'quantity' => $quantity,
            'expand' => ['latest_invoice.confirmation_secret'],
        ]);

        $this->fill([
            'stripe_status' => $stripeSubscription->status,
            'quantity' => $stripeSubscription->quantity,
        ])->save();

        $singleSubscriptionItem = $this->items()->firstOrFail();

        $singleSubscriptionItem->fill([
            'quantity' => $stripeSubscription->quantity,
        ])->save();

        $this->handlePaymentFailure($this);

        return $this;
    }

    /**
     * Report usage for a metered product.
     *
     * @param  int  $quantity
     * @param  \DateTimeInterface|int|null  $timestamp
     * @param  string|null  $price
     * @return \Stripe\V2\Billing\MeterEvent
     */
    public function reportUsage(int $quantity = 1, DateTimeInterface|int|null $timestamp = null, ?string $price = null)
    {
        if (! $price) {
            $this->guardAgainstMultiplePrices();
        }

        return $this->findItemOrFail($price ?? $this->stripe_price)->reportUsage($quantity, $timestamp);
    }

    /**
     * Report usage for specific price of a metered product.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  \DateTimeInterface|int|null  $timestamp
     * @return \Stripe\V2\Billing\MeterEvent
     */
    public function reportUsageFor(string $price, int $quantity = 1, DateTimeInterface|int|null $timestamp = null)
    {
        return $this->reportUsage($quantity, $timestamp, $price);
    }

    /**
     * Get the usage records for a metered product.
     *
     * @param  array  $options
     * @param  string|null  $price
     * @return \Illuminate\Support\Collection
     */
    public function usageRecords(array $options = [], ?string $price = null): Collection
    {
        if (! $price) {
            $this->guardAgainstMultiplePrices();
        }

        return $this->findItemOrFail($price ?? $this->stripe_price)->usageRecords($options);
    }

    /**
     * Get the usage records for a specific price of a metered product.
     *
     * @param  string  $price
     * @param  array  $options
     * @return \Illuminate\Support\Collection
     */
    public function usageRecordsFor(string $price, array $options = []): Collection
    {
        return $this->usageRecords($options, $price);
    }

    /**
     * Change the billing cycle anchor on a price change.
     *
     * @param  \DateTimeInterface|int|string  $date
     * @return $this
     */
    public function anchorBillingCycleOn(DateTimeInterface|int|string $date = 'now')
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        $this->billingCycleAnchor = $date;

        return $this;
    }

    /**
     * Set billing thresholds for the subscription.
     *
     * @param  array  $thresholds
     * @return $this
     */
    public function withBillingThresholds(array $thresholds)
    {
        $this->billingThresholds = $thresholds;

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
     * Force the subscription's trial to end immediately.
     *
     * @return $this
     */
    public function endTrial()
    {
        if (is_null($this->trial_ends_at)) {
            return $this;
        }

        $this->updateStripeSubscription([
            'trial_end' => 'now',
            'proration_behavior' => $this->prorateBehavior(),
        ]);

        $this->trial_ends_at = null;

        $this->save();

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

        $this->updateStripeSubscription([
            'trial_end' => $date->getTimestamp(),
            'proration_behavior' => $this->prorateBehavior(),
        ]);

        $this->trial_ends_at = $date;

        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to new Stripe prices.
     *
     * @param  string|array  $prices
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swap(string|array $prices, array $options = [])
    {
        if (empty($prices = (array) $prices)) {
            throw new InvalidArgumentException('Please provide at least one price when swapping.');
        }

        $this->guardAgainstIncomplete();

        $items = $this->mergeItemsThatShouldBeDeletedDuringSwap(
            $this->parseSwapPrices($prices)
        );

        $stripeSubscription = $this->owner->stripe()->subscriptions->update(
            $this->stripe_id, $this->getSwapOptions($items, $options)
        );

        /** @var \Stripe\SubscriptionItem $firstItem */
        $firstItem = $stripeSubscription->items->first();
        $isSinglePrice = $stripeSubscription->items->count() === 1;

        $this->fill([
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $isSinglePrice ? $firstItem->price->id : null,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'ends_at' => null,
        ])->save();

        $subscriptionItemIds = [];

        foreach ($stripeSubscription->items as $item) {
            $subscriptionItemIds[] = $item->id;

            $meterId = null;
            $meterEventName = null;

            if (isset($item->price->recurring->meter)) {
                $meterId = $item->price->recurring->meter;
                $meter = $this->owner->stripe()->billing->meters->retrieve($meterId);
                $meterEventName = $meter->event_name;
            }

            $this->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], [
                'stripe_product' => $item->price->product,
                'stripe_price' => $item->price->id,
                'meter_id' => $meterId,
                'quantity' => $item->quantity ?? null,
                'meter_event_name' => $meterEventName,
            ]);
        }

        // Delete items that aren't attached to the subscription anymore...
        $this->items()->whereNotIn('stripe_id', $subscriptionItemIds)->delete();

        $this->unsetRelation('items');

        $this->handlePaymentFailure($this);

        return $this;
    }

    /**
     * Swap the subscription to new Stripe prices, and invoice immediately.
     *
     * @param  string|array  $prices
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swapAndInvoice(string|array $prices, array $options = [])
    {
        $this->alwaysInvoice();

        return $this->swap($prices, $options);
    }

    /**
     * Parse the given prices for a swap operation.
     *
     * @param  array  $prices
     * @return \Illuminate\Support\Collection
     */
    protected function parseSwapPrices(array $prices): Collection
    {
        $isSinglePriceSwap = $this->hasSinglePrice() && count($prices) === 1;

        return Collection::make($prices)->mapWithKeys(function ($options, $price) use ($isSinglePriceSwap) {
            $price = is_string($options) ? $options : $price;

            $options = is_string($options) ? [] : $options;

            $payload = [
                'tax_rates' => $this->getPriceTaxRatesForPayload($price),
            ];

            if (! isset($options['price_data'])) {
                $payload['price'] = $price;
            }

            if ($isSinglePriceSwap && ! is_null($this->quantity)) {
                $payload['quantity'] = $this->quantity;
            }

            return [$price => array_merge($payload, $options)];
        });
    }

    /**
     * Merge the items that should be deleted during swap into the given items collection.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @return \Illuminate\Support\Collection
     */
    protected function mergeItemsThatShouldBeDeletedDuringSwap(Collection $items): Collection
    {
        /** @var \Stripe\SubscriptionItem $stripeSubscriptionItem */
        foreach ($this->asStripeSubscription()->items->data as $stripeSubscriptionItem) {
            $price = $stripeSubscriptionItem->price;

            if (! $item = $items->get($price->id, [])) {
                $item['deleted'] = true;

                if ($price->recurring->usage_type == 'metered') {
                    $item['clear_usage'] = true;
                }
            }

            $items->put($price->id, $item + ['id' => $stripeSubscriptionItem->id]);
        }

        return $items;
    }

    /**
     * Get the options array for a swap operation.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @param  array  $options
     * @return array
     */
    protected function getSwapOptions(Collection $items, array $options = []): array
    {
        $payload = array_filter([
            'items' => $items->values()->all(),
            'payment_behavior' => $this->paymentBehavior(),
            'proration_behavior' => $this->prorateBehavior(),
            'expand' => ['latest_invoice.confirmation_secret'],
        ]);

        // Add promotion code to discounts if set...
        if (! is_null($this->promotionCodeId)) {
            $payload['discounts'] = [['promotion_code' => $this->promotionCodeId]];
        }

        if ($payload['payment_behavior'] !== StripeSubscription::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE) {
            $payload['cancel_at_period_end'] = false;
        }

        $payload = array_merge($payload, $options);

        if (! is_null($this->billingCycleAnchor)) {
            $payload['billing_cycle_anchor'] = $this->billingCycleAnchor;
        }

        if (! is_null($this->billingThresholds)) {
            $payload['billing_thresholds'] = $this->billingThresholds;
        }

        $payload['trial_end'] = $this->onTrial()
                        ? $this->trial_ends_at->getTimestamp()
                        : 'now';

        return $payload;
    }

    /**
     * Add a new Stripe price to the subscription.
     *
     * @param  string  $price
     * @param  int|null  $quantity
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addPrice(string $price, ?int $quantity = 1, array $options = [])
    {
        $this->guardAgainstIncomplete();

        if ($this->items->contains('stripe_price', $price)) {
            throw SubscriptionUpdateFailure::duplicatePrice($this, $price);
        }

        $stripePrice = $this->owner->stripe()->prices->retrieve($price);

        $meterId = null;
        $meterEventName = null;

        if (isset($stripePrice->recurring->meter)) {
            $meterId = $stripePrice->recurring->meter;
            $meter = $this->owner->stripe()->billing->meters->retrieve($meterId);
            $meterEventName = $meter->event_name;
        }

        $stripeSubscriptionItem = $this->owner->stripe()->subscriptionItems
            ->create(array_filter(array_merge([
                'subscription' => $this->stripe_id,
                'price' => $price,
                'quantity' => $quantity,
                'tax_rates' => $this->getPriceTaxRatesForPayload($price),
                'payment_behavior' => $this->paymentBehavior(),
                'proration_behavior' => $this->prorateBehavior(),
            ], $options)));

        $this->items()->create([
            'stripe_id' => $stripeSubscriptionItem->id,
            'stripe_product' => $stripeSubscriptionItem->price->product,
            'stripe_price' => $stripeSubscriptionItem->price->id,
            'meter_id' => $meterId,
            'quantity' => $stripeSubscriptionItem->quantity ?? null,
            'meter_event_name' => $meterEventName,
        ]);

        $this->unsetRelation('items');

        $stripeSubscription = $this->asStripeSubscription();

        if ($this->hasSinglePrice()) {
            $this->fill([
                'stripe_price' => null,
                'quantity' => null,
            ]);
        }

        $this->fill([
            'stripe_status' => $stripeSubscription->status,
        ])->save();

        $this->handlePaymentFailure($this);

        return $this;
    }

    /**
     * Add a new Stripe price to the subscription, and invoice immediately.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addPriceAndInvoice(string $price, int $quantity = 1, array $options = [])
    {
        $this->alwaysInvoice();

        return $this->addPrice($price, $quantity, $options);
    }

    /**
     * Add a new Stripe metered price to the subscription.
     *
     * @param  string  $price
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addMeteredPrice(string $price, array $options = [])
    {
        return $this->addPrice($price, null, $options);
    }

    /**
     * Add a new Stripe metered price to the subscription, and invoice immediately.
     *
     * @param  string  $price
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addMeteredPriceAndInvoice(string $price, array $options = [])
    {
        return $this->addPriceAndInvoice($price, null, $options);
    }

    /**
     * Remove a Stripe price from the subscription.
     *
     * @param  string  $price
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function removePrice(string $price)
    {
        if ($this->hasSinglePrice()) {
            throw SubscriptionUpdateFailure::cannotDeleteLastPrice($this);
        }

        $stripeItem = $this->findItemOrFail($price)->asStripeSubscriptionItem();

        $stripeItem->delete(array_filter([
            'clear_usage' => $stripeItem->price->recurring->usage_type === 'metered' ? true : null,
            'proration_behavior' => $this->prorateBehavior(),
        ]));

        $this->items()->where('stripe_price', $price)->delete();

        $this->unsetRelation('items');

        if ($this->items()->count() < 2) {
            $item = $this->items()->first();

            $this->fill([
                'stripe_price' => $item->stripe_price,
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
        $stripeSubscription = $this->updateStripeSubscription([
            'cancel_at_period_end' => true,
        ]);

        $this->stripe_status = $stripeSubscription->status;

        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = $this->currentPeriodEnd();
        }

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription at a specific moment in time.
     *
     * @param  \DateTimeInterface|int  $endsAt
     * @return $this
     */
    public function cancelAt(DateTimeInterface|int $endsAt)
    {
        if ($endsAt instanceof DateTimeInterface) {
            $endsAt = $endsAt->getTimestamp();
        }

        $stripeSubscription = $this->updateStripeSubscription([
            'cancel_at' => $endsAt,
            'proration_behavior' => $this->prorateBehavior(),
        ]);

        $this->stripe_status = $stripeSubscription->status;

        $this->ends_at = Carbon::createFromTimestamp($stripeSubscription->cancel_at);

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately without invoicing.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $this->owner->stripe()->subscriptions->cancel($this->stripe_id, [
            'prorate' => $this->prorateBehavior() === 'create_prorations',
        ]);

        $this->markAsCanceled();

        return $this;
    }

    /**
     * Cancel the subscription immediately and invoice.
     *
     * @return $this
     */
    public function cancelNowAndInvoice()
    {
        $this->owner->stripe()->subscriptions->cancel($this->stripe_id, [
            'invoice_now' => true,
            'prorate' => $this->prorateBehavior() === 'create_prorations',
        ]);

        $this->markAsCanceled();

        return $this;
    }

    /**
     * Mark the subscription as canceled.
     *
     * @return void
     *
     * @internal
     */
    public function markAsCanceled(): void
    {
        $this->fill([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Resume the canceled subscription.
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

        $stripeSubscription = $this->updateStripeSubscription([
            'cancel_at_period_end' => false,
            'trial_end' => $this->onTrial() ? $this->trial_ends_at->getTimestamp() : 'now',
        ]);

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "canceled". Then we shall save this record in the database.
        $this->fill([
            'stripe_status' => $stripeSubscription->status,
            'ends_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Determine if the subscription has pending updates.
     *
     * @return bool
     */
    public function pending(): bool
    {
        return ! is_null($this->asStripeSubscription()->pending_update);
    }

    /**
     * Get the current period start date for the subscription.
     *
     * For multi-item subscriptions, returns the earliest start date.
     *
     * @param  \DateTimeZone|string|int|null  $timezone
     * @return \Carbon\CarbonInterface|null
     */
    public function currentPeriodStart(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return null;
        }

        $earliestStart = null;

        foreach ($items as $item) {
            $itemStart = $item->currentPeriodStart();

            if ($itemStart && (! $earliestStart || $itemStart->lt($earliestStart))) {
                $earliestStart = $itemStart;
            }
        }

        return $earliestStart ? ($timezone ? $earliestStart->setTimezone($timezone) : $earliestStart) : null;
    }

    /**
     * Get the current period end date for the subscription.
     *
     * For multi-item subscriptions, returns the latest end date.
     *
     * @param  \DateTimeZone|string|int|null  $timezone
     * @return \Carbon\CarbonInterface|null
     */
    public function currentPeriodEnd(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return null;
        }

        $latestEnd = null;

        foreach ($items as $item) {
            $itemEnd = $item->currentPeriodEnd();

            if ($itemEnd && (! $latestEnd || $itemEnd->gt($latestEnd))) {
                $latestEnd = $itemEnd;
            }
        }

        return $latestEnd ? ($timezone ? $latestEnd->setTimezone($timezone) : $latestEnd) : null;
    }

    /**
     * Invoice the subscription outside of the regular billing cycle.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoice(array $options = []): Invoice
    {
        try {
            return $this->user->invoice(array_merge($options, ['subscription' => $this->stripe_id]));
        } catch (IncompletePayment $exception) {
            // Set the new Stripe subscription status immediately when payment fails...
            $this->fill([
                'stripe_status' => $this->asStripeSubscription()->status,
            ])->save();

            throw $exception;
        }
    }

    /**
     * Get the latest invoice for the subscription.
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function latestInvoice(array $expand = []): ?Invoice
    {
        $stripeSubscription = $this->asStripeSubscription(['latest_invoice', ...$expand]);

        if ($stripeSubscription->latest_invoice) {
            return new Invoice($this->owner, $stripeSubscription->latest_invoice);
        }

        return null;
    }

    /**
     * Fetches upcoming invoice for this subscription.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice(array $options = []): ?Invoice
    {
        if ($this->canceled()) {
            return null;
        }

        return $this->owner->upcomingInvoice(array_merge([
            'subscription' => $this->stripe_id,
        ], $options));
    }

    /**
     * Preview the upcoming invoice with new Stripe prices.
     *
     * @param  string|array  $prices
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|null
     */
    public function previewInvoice(string|array $prices, array $options = []): ?Invoice
    {
        if (empty($prices = (array) $prices)) {
            throw new InvalidArgumentException('Please provide at least one price when swapping.');
        }

        $this->guardAgainstIncomplete();

        $items = $this->mergeItemsThatShouldBeDeletedDuringSwap(
            $this->parseSwapPrices($prices)
        );

        $swapOptions = Collection::make($this->getSwapOptions($items))
            ->only([
                'billing_cycle_anchor',
                'cancel_at_period_end',
                'items',
                'proration_behavior',
                'trial_end',
            ]);

        // For the new Create Preview Invoice API, we need to structure parameters correctly...
        $previewOptions = [
            'subscription' => $this->stripe_id,
            'subscription_details' => $swapOptions->all(),
        ];

        return $this->upcomingInvoice(array_merge($previewOptions, $options));
    }

    /**
     * Get a collection of the subscription's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\Invoice>
     */
    public function invoices(bool $includePending = false, array $parameters = []): Collection
    {
        return $this->owner->invoices(
            $includePending, array_merge($parameters, ['subscription' => $this->stripe_id])
        );
    }

    /**
     * Get an array of the subscription's invoices, including pending invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\Invoice>
     */
    public function invoicesIncludingPending(array $parameters = []): Collection
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Sync the tax rates of the user to the subscription.
     *
     * @return void
     */
    public function syncTaxRates(): void
    {
        $this->updateStripeSubscription([
            'default_tax_rates' => $this->user->taxRates() ?: null,
            'proration_behavior' => $this->prorateBehavior(),
        ]);

        foreach ($this->items as $item) {
            $item->updateStripeSubscriptionItem([
                'tax_rates' => $this->getPriceTaxRatesForPayload($item->stripe_price) ?: null,
                'proration_behavior' => $this->prorateBehavior(),
            ]);
        }
    }

    /**
     * Get the price tax rates for the Stripe payload.
     *
     * @param  string  $price
     * @return array|null
     */
    public function getPriceTaxRatesForPayload(string $price): ?array
    {
        if ($taxRates = $this->owner->priceTaxRates()) {
            return $taxRates[$price] ?? null;
        }

        return null;
    }

    /**
     * Determine if the subscription has an incomplete payment.
     *
     * @return bool
     */
    public function hasIncompletePayment(): bool
    {
        return $this->pastDue() || $this->incomplete();
    }

    /**
     * Get the latest payment for a Subscription.
     *
     * @return \Laravel\Cashier\Payment|null
     */
    public function latestPayment(): ?Payment
    {
        $subscription = $this->asStripeSubscription(['latest_invoice.payments']);

        if ($invoice = $subscription->latest_invoice) {
            if (isset($invoice->payments) && ! empty($invoice->payments->data)) {
                $latestPayment = end($invoice->payments->data);

                if ($latestPayment->payment && $latestPayment->payment->payment_intent) {
                    return new Payment(
                        $this->owner::stripe()->paymentIntents->retrieve($latestPayment->payment->payment_intent)
                    );
                }
            }
        }

        return null;
    }

    /**
     * The discount that applies to the subscription, if applicable.
     *
     * @return \Laravel\Cashier\Discount|null
     */
    public function discount(): ?Discount
    {
        $subscription = $this->asStripeSubscription(['discounts.promotion_code']);

        if (isset($subscription->discounts) && ! empty($subscription->discounts)) {
            return new Discount($subscription->discounts[0]);
        }

        return null;
    }

    /**
     * Get all discounts that apply to the subscription.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\Discount>
     */
    public function discounts(): Collection
    {
        $subscription = $this->asStripeSubscription(['discounts.promotion_code']);

        if (isset($subscription->discounts) && ! empty($subscription->discounts)) {
            return collect($subscription->discounts)->map(function ($discount) {
                return new Discount($discount);
            });
        }

        return collect();
    }

    /**
     * Apply a coupon to the subscription.
     *
     * @param  string  $couponId
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCoupon
     * @throws \Stripe\Exception\InvalidRequestException
     */
    public function applyCoupon(string $couponId): void
    {
        // Validate the coupon to ensure it's not a forever amount_off coupon...
        $this->validateCouponForSubscriptionApplication($couponId);

        $this->updateStripeSubscription([
            'discounts' => [['coupon' => $couponId]],
        ]);

        // Clear any cached discount data to ensure fresh data is retrieved...
        unset($this->discount, $this->discounts);
    }

    /**
     * Validate that a coupon can be applied to a subscription.
     *
     * @param  string  $couponId
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCoupon
     * @throws \Stripe\Exception\ApiErrorException
     */
    protected function validateCouponForSubscriptionApplication(string $couponId): void
    {
        $stripeCoupon = $this->owner::stripe()->coupons->retrieve($couponId);
        $coupon = new Coupon($stripeCoupon);

        if ($coupon->isForeverAmountOff()) {
            throw InvalidCoupon::cannotApplyForeverAmountOffToSubscription($couponId);
        }
    }

    /**
     * Apply a promotion code to the subscription.
     *
     * @param  string  $promotionCodeId
     * @return void
     */
    public function applyPromotionCode(string $promotionCodeId): void
    {
        $this->updateStripeSubscription([
            'discounts' => [['promotion_code' => $promotionCodeId]],
        ]);

        // Clear any cached discount data to ensure fresh data is retrieved...
        unset($this->discount, $this->discounts);
    }

    /**
     * Make sure a subscription is not incomplete when performing changes.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function guardAgainstIncomplete(): void
    {
        if ($this->incomplete()) {
            throw SubscriptionUpdateFailure::incompleteSubscription($this);
        }
    }

    /**
     * Make sure a price argument is provided when the subscription is a subscription with multiple prices.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function guardAgainstMultiplePrices(): void
    {
        if ($this->hasMultiplePrices()) {
            throw new InvalidArgumentException(
                'This method requires a price argument since the subscription has multiple prices.'
            );
        }
    }

    /**
     * Update the underlying Stripe subscription information for the model.
     *
     * @param  array  $options
     * @return \Stripe\Subscription
     */
    public function updateStripeSubscription(array $options = [])
    {
        return $this->owner->stripe()->subscriptions->update(
            $this->stripe_id, $options
        );
    }

    /**
     * Get the subscription as a Stripe subscription object.
     *
     * @param  array  $expand
     * @return \Stripe\Subscription
     */
    public function asStripeSubscription(array $expand = [])
    {
        return $this->owner->stripe()->subscriptions->retrieve(
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
        return SubscriptionFactory::new();
    }
}
