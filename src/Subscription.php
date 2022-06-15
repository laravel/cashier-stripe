<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\AllowsCoupons;
use Laravel\Cashier\Concerns\InteractsWithPaymentBehavior;
use Laravel\Cashier\Concerns\Prorates;
use Laravel\Cashier\Database\Factories\SubscriptionFactory;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use LogicException;
use Stripe\Subscription as StripeSubscription;

/**
 * @property \Laravel\Cashier\Billable|\Illuminate\Database\Eloquent\Model $owner
 */
class Subscription extends Model
{
    use AllowsCoupons;
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
        'quantity' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'ends_at',
        'trial_ends_at',
        'updated_at',
    ];

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
        $model = Cashier::$customerModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription items related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(Cashier::$subscriptionItemModel);
    }

    /**
     * Determine if the subscription has multiple prices.
     *
     * @return bool
     */
    public function hasMultiplePrices()
    {
        return is_null($this->stripe_price);
    }

    /**
     * Determine if the subscription has a single price.
     *
     * @return bool
     */
    public function hasSinglePrice()
    {
        return ! $this->hasMultiplePrices();
    }

    /**
     * Determine if the subscription has a specific product.
     *
     * @param  string  $product
     * @return bool
     */
    public function hasProduct($product)
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
    public function hasPrice($price)
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
    public function findItemOrFail($price)
    {
        return $this->items()->where('stripe_price', $price)->firstOrFail();
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
        return ! $this->ended() &&
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
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Filter query by recurring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeRecurring($query)
    {
        $query->notOnTrial()->notCanceled();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function canceled()
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     *
     * @deprecated Use canceled instead.
     */
    public function cancelled()
    {
        return $this->canceled();
    }

    /**
     * Filter query by canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeCanceled($query)
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Filter query by canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     *
     * @deprecated Use scopeCanceled instead.
     */
    public function scopeCancelled($query)
    {
        $this->scopeCanceled($query);
    }

    /**
     * Filter query by not canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotCanceled($query)
    {
        $query->whereNull('ends_at');
    }

    /**
     * Filter query by not canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     *
     * @deprecated Use scopeNotCanceled instead.
     */
    public function scopeNotCancelled($query)
    {
        $this->scopeNotCanceled($query);
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeEnded($query)
    {
        $query->canceled()->notOnGracePeriod();
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
     * Determine if the subscription's trial has expired.
     *
     * @return bool
     */
    public function hasExpiredTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter query by expired trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeExpiredTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
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
     * @param  string|null  $price
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementQuantity($count = 1, $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->setProrationBehavior($this->prorationBehavior)->incrementQuantity($count);

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
    public function incrementAndInvoice($count = 1, $price = null)
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
    public function decrementQuantity($count = 1, $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->setProrationBehavior($this->prorationBehavior)->decrementQuantity($count);

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
    public function updateQuantity($quantity, $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->setProrationBehavior($this->prorationBehavior)->updateQuantity($quantity);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        $stripeSubscription = $this->updateStripeSubscription([
            'payment_behavior' => $this->paymentBehavior(),
            'proration_behavior' => $this->prorateBehavior(),
            'quantity' => $quantity,
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        $this->fill([
            'stripe_status' => $stripeSubscription->status,
            'quantity' => $stripeSubscription->quantity,
        ])->save();

        if ($this->hasIncompletePayment()) {
            (new Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
        }

        return $this;
    }

    /**
     * Report usage for a metered product.
     *
     * @param  int  $quantity
     * @param  \DateTimeInterface|int|null  $timestamp
     * @param  string|null  $price
     * @return \Stripe\UsageRecord
     */
    public function reportUsage($quantity = 1, $timestamp = null, $price = null)
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
     * @return \Stripe\UsageRecord
     */
    public function reportUsageFor($price, $quantity = 1, $timestamp = null)
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
    public function usageRecords(array $options = [], $price = null)
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
    public function usageRecordsFor($price, array $options = [])
    {
        return $this->usageRecords($options, $price);
    }

    /**
     * Change the billing cycle anchor on a price change.
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
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swap($prices, array $options = [])
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

        foreach ($stripeSubscription->items as $item) {
            $this->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], [
                'stripe_product' => $item->price->product,
                'stripe_price' => $item->price->id,
                'quantity' => $item->quantity ?? null,
            ]);
        }

        // Delete items that aren't attached to the subscription anymore...
        $this->items()->whereNotIn('stripe_price', $items->pluck('price')->filter())->delete();

        $this->unsetRelation('items');

        if ($this->hasIncompletePayment()) {
            (new Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
        }

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
    public function swapAndInvoice($prices, array $options = [])
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
    protected function parseSwapPrices(array $prices)
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
    protected function mergeItemsThatShouldBeDeletedDuringSwap(Collection $items)
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
    protected function getSwapOptions(Collection $items, array $options = [])
    {
        $payload = array_filter([
            'items' => $items->values()->all(),
            'payment_behavior' => $this->paymentBehavior(),
            'promotion_code' => $this->promotionCodeId,
            'proration_behavior' => $this->prorateBehavior(),
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        if ($payload['payment_behavior'] !== StripeSubscription::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE) {
            $payload['cancel_at_period_end'] = false;
        }

        $payload = array_merge($payload, $options);

        if (! is_null($this->billingCycleAnchor)) {
            $payload['billing_cycle_anchor'] = $this->billingCycleAnchor;
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
    public function addPrice($price, $quantity = 1, array $options = [])
    {
        $this->guardAgainstIncomplete();

        if ($this->items->contains('stripe_price', $price)) {
            throw SubscriptionUpdateFailure::duplicatePrice($this, $price);
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
            'quantity' => $stripeSubscriptionItem->quantity ?? null,
        ]);

        $this->unsetRelation('items');

        if ($this->hasSinglePrice()) {
            $this->fill([
                'stripe_price' => null,
                'quantity' => null,
            ])->save();
        }

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
    public function addPriceAndInvoice($price, $quantity = 1, array $options = [])
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
    public function addMeteredPrice($price, array $options = [])
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
    public function addMeteredPriceAndInvoice($price, array $options = [])
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
    public function removePrice($price)
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
            $this->ends_at = Carbon::createFromTimestamp(
                $stripeSubscription->current_period_end
            );
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
    public function cancelAt($endsAt)
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
    public function markAsCanceled()
    {
        $this->fill([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Mark the subscription as canceled.
     *
     * @return void
     *
     * @deprecated Use markAsCanceled instead.
     *
     * @internal
     */
    public function markAsCancelled()
    {
        $this->markAsCanceled();
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
    public function pending()
    {
        return ! is_null($this->asStripeSubscription()->pending_update);
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
     * Get the latest invoice for the subscription.
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function latestInvoice()
    {
        $stripeSubscription = $this->asStripeSubscription(['latest_invoice']);

        if ($stripeSubscription->latest_invoice) {
            return new Invoice($this->owner, $stripeSubscription->latest_invoice);
        }
    }

    /**
     * Fetches upcoming invoice for this subscription.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice(array $options = [])
    {
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
    public function previewInvoice($prices, array $options = [])
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
            ])
            ->mapWithKeys(function ($value, $key) {
                return ["subscription_$key" => $value];
            })
            ->merge($options)
            ->all();

        return $this->upcomingInvoice($swapOptions);
    }

    /**
     * Get a collection of the subscription's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\Invoice[]
     */
    public function invoices($includePending = false, $parameters = [])
    {
        return $this->owner->invoices(
            $includePending, array_merge($parameters, ['subscription' => $this->stripe_id])
        );
    }

    /**
     * Get an array of the subscription's invoices, including pending invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\Invoice[]
     */
    public function invoicesIncludingPending(array $parameters = [])
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Sync the tax rates of the user to the subscription.
     *
     * @return void
     */
    public function syncTaxRates()
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
    public function getPriceTaxRatesForPayload($price)
    {
        if ($taxRates = $this->owner->priceTaxRates()) {
            return $taxRates[$price] ?? null;
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
        $subscription = $this->asStripeSubscription(['latest_invoice.payment_intent']);

        if ($invoice = $subscription->latest_invoice) {
            return $invoice->payment_intent
                ? new Payment($invoice->payment_intent)
                : null;
        }
    }

    /**
     * The discount that applies to the subscription, if applicable.
     *
     * @return \Laravel\Cashier\Discount|null
     */
    public function discount()
    {
        $subscription = $this->asStripeSubscription(['discount.promotion_code']);

        return $subscription->discount
            ? new Discount($subscription->discount)
            : null;
    }

    /**
     * Apply a coupon to the subscription.
     *
     * @param  string  $coupon
     * @return void
     */
    public function applyCoupon($coupon)
    {
        $this->updateStripeSubscription([
            'coupon' => $coupon,
        ]);
    }

    /**
     * Apply a promotion code to the subscription.
     *
     * @param  string  $promotionCodeId
     * @return void
     */
    public function applyPromotionCode($promotionCodeId)
    {
        $this->updateStripeSubscription([
            'promotion_code' => $promotionCodeId,
        ]);
    }

    /**
     * Make sure a subscription is not incomplete when performing changes.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function guardAgainstIncomplete()
    {
        if ($this->incomplete()) {
            throw SubscriptionUpdateFailure::incompleteSubscription($this);
        }
    }

    /**
     * Make sure a price argument is provided when the subscription is a multi price subscription.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function guardAgainstMultiplePrices()
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
