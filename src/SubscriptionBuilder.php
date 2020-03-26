<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Stripe\Subscription as StripeSubscription;

class SubscriptionBuilder
{
    /**
     * The model that is subscribing.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The name of the subscription.
     *
     * @var string
     */
    protected $name;

    /**
     * The name of the plan being subscribed to.
     *
     * @var array
     */
    protected $items;

    /**
     * The date and time the trial will expire.
     *
     * @var \Carbon\Carbon|\Carbon\CarbonInterface
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
     * The coupon code being applied to the customer.
     *
     * @var string|null
     */
    protected $coupon;

    /**
     * The metadata to apply to the subscription.
     *
     * @var array|null
     */
    protected $metadata;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $name
     * @param  string|array  $plans
     * @return void
     */
    public function __construct($owner, $name, $plans = null)
    {
        $this->name = $name;
        $this->owner = $owner;
        $this->items = $this->formatPlans((array) $plans);
    }

    /**
     * Formats the given Stripe Plan IDs into an associative array.
     *
     * @param string[] $plans
     * @return array
     */
    protected function formatPlans(array $plans)
    {
        return collect($plans)->mapWithKeys(function ($plan) {
            return [$plan => [
                'plan' => $plan,
                'quantity' => 1,
            ]];
        })->all();
    }

    /**
     * Set a plan on the subscription builder.
     *
     * @param  string  $plan
     * @param  int  $quantity
     * @return $this
     */
    public function plan($plan, $quantity = 1)
    {
        $this->items[$plan] = [
            'plan' => $plan,
            'quantity' => $quantity,
        ];

        return $this;
    }

    /**
     * Specify the quantity of a subscription item.
     *
     * @param  int  $quantity
     * @param  string|null  $plan
     * @return $this
     */
    public function quantity($quantity, $plan = null)
    {
        if (is_null($plan)) {
            if (count($this->items) > 1) {
                throw new InvalidArgumentException('Plan is required when creating multi plan subscriptions.');
            }

            $plan = Arr::first($this->items)['plan'];
        }

        return $this->plan($plan, $quantity);
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
     * Change the billing cycle anchor on a plan creation.
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
     * The coupon to apply to a new subscription.
     *
     * @param  string  $coupon
     * @return $this
     */
    public function withCoupon($coupon)
    {
        $this->coupon = $coupon;

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
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Add a new Stripe subscription to the Stripe model.
     *
     * @param  array  $customerOptions
     * @param  array  $subscriptionOptions
     * @return \Laravel\Cashier\Subscription
     *
     * @throws \Laravel\Cashier\Exceptions\PaymentActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
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
     * @throws \Laravel\Cashier\Exceptions\PaymentActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
     */
    public function create($paymentMethod = null, array $customerOptions = [], array $subscriptionOptions = [])
    {
        $customer = $this->getStripeCustomer($paymentMethod, $customerOptions);

        $payload = array_merge(
            ['customer' => $customer->id],
            $this->buildPayload(),
            $subscriptionOptions
        );

        $stripeSubscription = StripeSubscription::create(
            $payload,
            $this->owner->stripeOptions()
        );

        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialExpires;
        }

        /** @var \Laravel\Cashier\Subscription $subscription */
        $subscription = $this->owner->subscriptions()->create([
            'name' => $this->name,
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
            'stripe_plan' => count($this->items) === 1 ? Arr::first($this->items)['plan'] : null,
            'quantity' => count($this->items) === 1 ? Arr::first($this->items)['quantity'] : null,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);

        if ($subscription->incomplete()) {
            (new Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
        }

        return $subscription;
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
        return array_filter([
            'billing_cycle_anchor' => $this->billingCycleAnchor,
            'coupon' => $this->coupon,
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $this->metadata,
            'items' => collect($this->items)->values()->all(),
            'default_tax_rates' => $this->getTaxRatesForPayload(),
            'trial_end' => $this->getTrialEndForPayload(),
            'off_session' => true,
        ]);
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
}
