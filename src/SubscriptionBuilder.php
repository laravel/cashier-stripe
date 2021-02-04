<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Cashier\Concerns\InteractsWithPaymentBehavior;
use Laravel\Cashier\Concerns\Prorates;
use Stripe\Subscription as StripeSubscription;

class SubscriptionBuilder
{
    use InteractsWithPaymentBehavior;
    use Prorates;

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
     * The coupon being applied to the subscription.
     *
     * @var string|null
     */
    protected $coupon;

    /**
     * The promotion code being applied to the subscription.
     *
     * @var string|null
     */
    protected $promotionCode;

    /**
     * Determines if user redeemable promotion codes are available in Stripe Checkout.
     *
     * @var bool
     */
    protected $allowPromotionCodes = false;

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
     * @param  string|string[]  $plans
     * @return void
     */
    public function __construct($owner, $name, $plans = [])
    {
        $this->name = $name;
        $this->owner = $owner;

        foreach ((array) $plans as $plan) {
            $this->plan($plan);
        }
    }

    /**
     * Set a plan on the subscription builder.
     *
     * @param  string  $plan
     * @param  int|null  $quantity
     * @return $this
     */
    public function plan($plan, $quantity = 1)
    {
        $options = [
            'price' => $plan,
            'quantity' => $quantity,
        ];

        if ($taxRates = $this->getPlanTaxRatesForPayload($plan)) {
            $options['tax_rates'] = $taxRates;
        }

        $this->items[$plan] = $options;

        return $this;
    }

    /**
     * Set a metered plan on the subscription builder.
     *
     * @param  string  $plan
     * @return $this
     */
    public function meteredPlan($plan)
    {
        return $this->plan($plan, null);
    }

    /**
     * Specify the quantity of a subscription item.
     *
     * @param  int|null  $quantity
     * @param  string|null  $plan
     * @return $this
     */
    public function quantity($quantity, $plan = null)
    {
        if (is_null($plan)) {
            if (count($this->items) > 1) {
                throw new InvalidArgumentException('Plan is required when creating multi-plan subscriptions.');
            }

            $plan = Arr::first($this->items)['price'];
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
     * The promotion code to apply to a new subscription.
     *
     * @param  string  $promotionCode
     * @return $this
     */
    public function withPromotionCode($promotionCode)
    {
        $this->promotionCode = $promotionCode;

        return $this;
    }

    /**
     * Enables user redeemable promotion codes.
     *
     * @return $this
     */
    public function allowPromotionCodes()
    {
        $this->allowPromotionCodes = true;

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
     * @throws \Exception
     * @throws \Laravel\Cashier\Exceptions\PaymentActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
     */
    public function create($paymentMethod = null, array $customerOptions = [], array $subscriptionOptions = [])
    {
        if (empty($this->items)) {
            throw new Exception('At least one plan is required when starting subscriptions.');
        }

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

        /** @var \Stripe\SubscriptionItem $firstItem */
        $firstItem = $stripeSubscription->items->first();
        $isSinglePlan = $stripeSubscription->items->count() === 1;

        /** @var \Laravel\Cashier\Subscription $subscription */
        $subscription = $this->owner->subscriptions()->create([
            'name' => $this->name,
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
            'stripe_plan' => $isSinglePlan ? $firstItem->plan->id : null,
            'quantity' => $isSinglePlan ? $firstItem->quantity : null,
            'trial_ends_at' => ! $this->skipTrial ? $this->trialExpires : null,
            'ends_at' => null,
        ]);

        /** @var \Stripe\SubscriptionItem $item */
        foreach ($stripeSubscription->items as $item) {
            $subscription->items()->create([
                'stripe_id' => $item->id,
                'stripe_plan' => $item->plan->id,
                'quantity' => $item->quantity,
            ]);
        }

        if ($subscription->hasIncompletePayment()) {
            (new Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
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
            throw new Exception('At least one plan is required when starting subscriptions.');
        }

        if (! $this->skipTrial && $this->trialExpires) {
            // Checkout Sessions are active for 24 hours after their creation and within that time frame the customer
            // can complete the payment at any time. Stripe requires the trial end at least 48 hours in the future
            // so that there is still at least a one day trial if your customer pays at the end of the 24 hours.
            $minimumTrialPeriod = Carbon::now()->addHours(48);

            $trialEnd = $this->trialExpires->gt($minimumTrialPeriod) ? $this->trialExpires : $minimumTrialPeriod;
        } else {
            $trialEnd = null;
        }

        return Checkout::create($this->owner, array_merge([
            'mode' => 'subscription',
            'line_items' => collect($this->items)->values()->all(),
            'allow_promotion_codes' => $this->allowPromotionCodes,
            'discounts' => [
                'coupon' => $this->coupon,
            ],
            'subscription_data' => [
                'default_tax_rates' => $this->getTaxRatesForPayload(),
                'trial_end' => $trialEnd ? $trialEnd->getTimestamp() : null,
                'metadata' => array_merge($this->metadata, ['name' => $this->name]),
            ],
        ], $sessionOptions), $customerOptions);
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
            'billing_cycle_anchor' => $this->billingCycleAnchor,
            'coupon' => $this->coupon,
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $this->metadata,
            'items' => collect($this->items)->values()->all(),
            'payment_behavior' => $this->paymentBehavior(),
            'promotion_code' => $this->promotionCode,
            'proration_behavior' => $this->prorateBehavior(),
            'trial_end' => $this->getTrialEndForPayload(),
            'off_session' => true,
        ]);

        if ($taxRates = $this->getTaxRatesForPayload()) {
            $payload['default_tax_rates'] = $taxRates;
        } elseif ($taxPercentage = $this->getTaxPercentageForPayload()) {
            $payload['tax_percent'] = $taxPercentage;
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
     * Get the tax percentage for the Stripe payload.
     *
     * @return int|float|null
     * @deprecated Please migrate to the new Tax Rates API.
     */
    protected function getTaxPercentageForPayload()
    {
        if ($taxPercentage = $this->owner->taxPercentage()) {
            return $taxPercentage;
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
}
