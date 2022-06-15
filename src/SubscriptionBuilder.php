<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\AllowsCoupons;
use Laravel\Cashier\Concerns\HandlesTaxes;
use Laravel\Cashier\Concerns\InteractsWithPaymentBehavior;
use Laravel\Cashier\Concerns\Prorates;
use Stripe\Subscription as StripeSubscription;

class SubscriptionBuilder
{
    use AllowsCoupons;
    use HandlesTaxes;
    use InteractsWithPaymentBehavior;
    use Prorates;

    /**
     * The model that is subscribing.
     *
     * @var \Laravel\Cashier\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The name of the subscription.
     *
     * @var string
     */
    protected $name;

    /**
     * The prices the customer is being subscribed to.
     *
     * @var array
     */
    protected $items = [];

    /**
     * The date and time the trial will expire.
     *
     * @var \Carbon\Carbon|\Carbon\CarbonInterface|null
     */
    protected $trialExpires;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var int|null
     */
    protected $billingCycleAnchor = null;

    /**
     * The metadata to apply to the subscription.
     *
     * @var array
     */
    protected $metadata = [];

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $name
     * @param  string|string[]|array[]  $prices
     * @return void
     */
    public function __construct($owner, $name, $prices = [])
    {
        $this->name = $name;
        $this->owner = $owner;

        foreach ((array) $prices as $price) {
            $this->price($price);
        }
    }

    /**
     * Set a price on the subscription builder.
     *
     * @param  string|array  $price
     * @param  int|null  $quantity
     * @return $this
     */
    public function price($price, $quantity = 1)
    {
        $options = is_array($price) ? $price : ['price' => $price];

        $quantity = $price['quantity'] ?? $quantity;

        if (! is_null($quantity)) {
            $options['quantity'] = $quantity;
        }

        if ($taxRates = $this->getPriceTaxRatesForPayload($price)) {
            $options['tax_rates'] = $taxRates;
        }

        if (isset($options['price'])) {
            $this->items[$options['price']] = $options;
        } else {
            $this->items[] = $options;
        }

        return $this;
    }

    /**
     * Set a metered price on the subscription builder.
     *
     * @param  string  $price
     * @return $this
     */
    public function meteredPrice($price)
    {
        return $this->price($price, null);
    }

    /**
     * Specify the quantity of a subscription item.
     *
     * @param  int|null  $quantity
     * @param  string|null  $price
     * @return $this
     */
    public function quantity($quantity, $price = null)
    {
        if (is_null($price)) {
            if (count($this->items) > 1) {
                throw new InvalidArgumentException('Price is required when creating multi-price subscriptions.');
            }

            $price = Arr::first($this->items)['price'];
        }

        return $this->price($price, $quantity);
    }

    /**
     * Specify the number of days of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays($trialDays)
    {
        $this->trialExpires = Carbon::now()->addDays($trialDays);

        return $this;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  \Carbon\Carbon|\Carbon\CarbonInterface  $trialUntil
     * @return $this
     */
    public function trialUntil($trialUntil)
    {
        $this->trialExpires = $trialUntil;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * Change the billing cycle anchor on a subscription creation.
     *
     * @param  \DateTimeInterface|int  $date
     * @return $this
     */
    public function anchorBillingCycleOn($date)
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        $this->billingCycleAnchor = $date;

        return $this;
    }

    /**
     * The metadata to apply to a new subscription.
     *
     * @param  array  $metadata
     * @return $this
     */
    public function withMetadata($metadata)
    {
        $this->metadata = (array) $metadata;

        return $this;
    }

    /**
     * Add a new Stripe subscription to the Stripe model.
     *
     * @param  array  $customerOptions
     * @param  array  $subscriptionOptions
     * @return \Laravel\Cashier\Subscription
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function add(array $customerOptions = [], array $subscriptionOptions = [])
    {
        return $this->create(null, $customerOptions, $subscriptionOptions);
    }

    /**
     * Create a new Stripe subscription.
     *
     * @param  \Stripe\PaymentMethod|string|null  $paymentMethod
     * @param  array  $customerOptions
     * @param  array  $subscriptionOptions
     * @return \Laravel\Cashier\Subscription
     *
     * @throws \Exception
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function create($paymentMethod = null, array $customerOptions = [], array $subscriptionOptions = [])
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        $stripeCustomer = $this->getStripeCustomer($paymentMethod, $customerOptions);

        $stripeSubscription = $this->owner->stripe()->subscriptions->create(array_merge(
            ['customer' => $stripeCustomer->id],
            $this->buildPayload(),
            $subscriptionOptions
        ));

        $subscription = $this->createSubscription($stripeSubscription);

        if ($subscription->hasIncompletePayment()) {
            (new Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
        }

        return $subscription;
    }

    /**
     * Create a new Stripe subscription and send an invoice to the customer.
     *
     * @param  array  $customerOptions
     * @param  array  $subscriptionOptions
     * @return \Laravel\Cashier\Subscription
     *
     * @throws \Exception
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function createAndSendInvoice(array $customerOptions = [], array $subscriptionOptions = [])
    {
        return $this->create(null, $customerOptions, array_merge($subscriptionOptions, [
            'collection_method' => 'send_invoice',
        ]));
    }

    /**
     * Create the Eloquent Subscription.
     *
     * @param  \Stripe\Subscription  $stripeSubscription
     * @return \Laravel\Cashier\Subscription
     */
    protected function createSubscription(StripeSubscription $stripeSubscription)
    {
        if ($subscription = $this->owner->subscriptions()->where('stripe_id', $stripeSubscription->id)->first()) {
            return $subscription;
        }

        /** @var \Stripe\SubscriptionItem $firstItem */
        $firstItem = $stripeSubscription->items->first();
        $isSinglePrice = $stripeSubscription->items->count() === 1;

        /** @var \Laravel\Cashier\Subscription $subscription */
        $subscription = $this->owner->subscriptions()->create([
            'name' => $this->name,
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $isSinglePrice ? $firstItem->price->id : null,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'trial_ends_at' => ! $this->skipTrial ? $this->trialExpires : null,
            'ends_at' => null,
        ]);

        /** @var \Stripe\SubscriptionItem $item */
        foreach ($stripeSubscription->items as $item) {
            $subscription->items()->create([
                'stripe_id' => $item->id,
                'stripe_product' => $item->price->product,
                'stripe_price' => $item->price->id,
                'quantity' => $item->quantity ?? null,
            ]);
        }

        return $subscription;
    }

    /**
     * Begin a new Checkout Session.
     *
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function checkout(array $sessionOptions = [], array $customerOptions = [])
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        if (! $this->skipTrial && $this->trialExpires) {
            // Checkout Sessions are active for 24 hours after their creation and within that time frame the customer
            // can complete the payment at any time. Stripe requires the trial end at least 48 hours in the future
            // so that there is still at least a one day trial if your customer pays at the end of the 24 hours.
            // We also add 10 seconds of extra time to account for any delay with an API request onto Stripe.
            $minimumTrialPeriod = Carbon::now()->addHours(48)->addSeconds(10);

            $trialEnd = $this->trialExpires->gt($minimumTrialPeriod) ? $this->trialExpires : $minimumTrialPeriod;
        } else {
            $trialEnd = null;
        }

        $payload = array_filter([
            'automatic_tax' => $this->automaticTaxPayload(),
            'mode' => 'subscription',
            'line_items' => Collection::make($this->items)->values()->all(),
            'allow_promotion_codes' => $this->allowPromotionCodes,
            'discounts' => $this->checkoutDiscounts(),
            'subscription_data' => array_filter([
                'default_tax_rates' => $this->getTaxRatesForPayload(),
                'trial_end' => $trialEnd ? $trialEnd->getTimestamp() : null,
                'metadata' => array_merge($this->metadata, ['name' => $this->name]),
            ]),
            'tax_id_collection' => [
                'enabled' => Cashier::$calculatesTaxes ?: $this->collectTaxIds,
            ],
        ]);

        return Checkout::create($this->owner, array_merge($payload, $sessionOptions), $customerOptions);
    }

    /**
     * Get the Stripe customer instance for the current user and payment method.
     *
     * @param  \Stripe\PaymentMethod|string|null  $paymentMethod
     * @param  array  $options
     * @return \Stripe\Customer
     */
    protected function getStripeCustomer($paymentMethod = null, array $options = [])
    {
        $customer = $this->owner->createOrGetStripeCustomer($options);

        if ($paymentMethod) {
            $this->owner->updateDefaultPaymentMethod($paymentMethod);
        }

        return $customer;
    }

    /**
     * Build the payload for subscription creation.
     *
     * @return array
     */
    protected function buildPayload()
    {
        $payload = array_filter([
            'automatic_tax' => $this->automaticTaxPayload(),
            'billing_cycle_anchor' => $this->billingCycleAnchor,
            'coupon' => $this->couponId,
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $this->metadata,
            'items' => Collection::make($this->items)->values()->all(),
            'payment_behavior' => $this->paymentBehavior(),
            'promotion_code' => $this->promotionCodeId,
            'proration_behavior' => $this->prorateBehavior(),
            'trial_end' => $this->getTrialEndForPayload(),
            'off_session' => true,
        ]);

        if ($taxRates = $this->getTaxRatesForPayload()) {
            $payload['default_tax_rates'] = $taxRates;
        }

        return $payload;
    }

    /**
     * Get the trial ending date for the Stripe payload.
     *
     * @return int|string|null
     */
    protected function getTrialEndForPayload()
    {
        if ($this->skipTrial) {
            return 'now';
        }

        if ($this->trialExpires) {
            return $this->trialExpires->getTimestamp();
        }
    }

    /**
     * Get the tax rates for the Stripe payload.
     *
     * @return array|null
     */
    protected function getTaxRatesForPayload()
    {
        if ($taxRates = $this->owner->taxRates()) {
            return $taxRates;
        }
    }

    /**
     * Get the price tax rates for the Stripe payload.
     *
     * @param  string  $price
     * @return array|null
     */
    protected function getPriceTaxRatesForPayload($price)
    {
        if ($taxRates = $this->owner->priceTaxRates()) {
            return $taxRates[$price] ?? null;
        }
    }

    /**
     * Get the items set on the subscription builder.
     *
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }
}
