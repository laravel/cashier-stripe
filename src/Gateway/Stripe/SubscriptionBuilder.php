<?php

namespace Laravel\Cashier\Gateway\Stripe;

use Carbon\Carbon;
use Laravel\Cashier\SubscriptionBuilder as BaseBuilder;

class SubscriptionBuilder extends BaseBuilder
{
    /**
     * The quantity of the subscription.
     *
     * @var int
     */
    protected $quantity = 1;

    /**
     * @var Carbon
     */
    protected $trialExpires;

    /**
     * The metadata to apply to the subscription.
     *
     * @var array|null
     */
    protected $metadata;

    /**
     * Specify the quantity of the subscription.
     *
     * @param  int  $quantity
     * @return $this
     */
    public function quantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
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
     * @param  \Carbon\Carbon  $trialUntil
     * @return $this
     */
    public function trialUntil(Carbon $trialUntil)
    {
        $this->trialExpires = $trialUntil;

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
     * Create a new Stripe subscription.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Laravel\Cashier\Subscription
     * @throws \Laravel\Cashier\Gateway\Stripe\Exception
     */
    public function create($token = null, array $options = [])
    {
        $customer = $this->getStripeCustomer($token, $options);

        // TODO: How to handle Cashier Exceptions vs. Stripe Errors
        $subscription = $customer->subscriptions->create($this->buildPayload());

        return $this->owner->subscriptions()->create([
            'name' => $this->name,
            'stripe_id' => $subscription->id, // FIXME
            'stripe_plan' => $this->plan, // FIXME
            'quantity' => $this->quantity,
            'trial_ends_at' => ! $this->skipTrial && $this->trialExpires ? $this->trialExpires : null,
            'ends_at' => null,
        ]);
    }

    /**
     * Get the Stripe customer instance for the current user and token.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Stripe\Customer
     * @throws \Laravel\Cashier\Gateway\Stripe\Exception
     */
    protected function getStripeCustomer($token = null, array $options = [])
    {
        if (isset($this->owner->payment_gateway) && 'stripe' !== $this->owner->payment_gateway) {
            throw new Exception('Customer is not using the Stripe payment gateway.');
        }

        return $this->getCustomerForGateway('stripe', $token, $options);
    }

    /**
     * Build the payload for subscription creation.
     *
     * @return array
     */
    protected function buildPayload()
    {
        return array_filter([
            'plan' => $this->plan,
            'quantity' => $this->quantity,
            'coupon' => $this->coupon,
            'trial_end' => $this->getTrialEndForPayload(),
            'tax_percent' => $this->getTaxPercentageForPayload(),
            'metadata' => $this->metadata,
        ]);
    }

    /**
     * Get the trial ending date for the Stripe payload.
     *
     * @return int|null
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
     * @return int|null
     */
    protected function getTaxPercentageForPayload()
    {
        if ($taxPercentage = $this->owner->taxPercentage()) {
            return $taxPercentage;
        }
    }
}
