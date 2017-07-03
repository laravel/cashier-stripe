<?php

namespace Laravel\Cashier\Gateway\Braintree;

use Braintree\Customer;
use Braintree\Subscription as BraintreeSubscription;
use Carbon\Carbon;
use Laravel\Cashier\SubscriptionBuilder as BaseBuilder;

class SubscriptionBuilder extends BaseBuilder
{
    /**
     * The Braintree customer for this subscription.
     *
     * @var \Braintree\Customer
     */
    protected $braintreeCustomer;

    /**
     * @param  string $token
     * @param  array $options
     * @return static
     */
    public function customer($token, array $options = [])
    {
        $this->braintreeCustomer = $this->getBraintreeCustomer($token, $options);

        return $this;
    }

    /**
     * Create a new Braintree subscription.
     *
     * @param  string|null $token
     * @param  array $subscriptionOptions
     * @return \Laravel\Cashier\Subscription
     * @throws \Laravel\Cashier\Gateway\Braintree\Exception
     */
    public function create($token = null, array $subscriptionOptions = [])
    {
        $this->setBraintreeCustomerFromArgs(func_get_args());
        $payload = $this->getSubscriptionPayload($this->braintreeCustomer, $subscriptionOptions);

        if ($this->coupon) {
            $payload = $this->addCouponToPayload($payload);
        }

        $response = BraintreeSubscription::create($payload);

        if (! $response->success) {
            throw new Exception('Braintree failed to create subscription: '.$response->message);
        }

        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialDays ? Carbon::now()->addDays($this->trialDays) : null;
        }

        return $this->owner->subscriptions()->create([
            'name' => $this->name,
            'braintree_id' => $response->subscription->id,
            'braintree_plan' => $this->plan,
            'quantity' => 1,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);
    }

    /**
     * Sets the braintree customer in a backwards-compatible way.
     *
     * @param  array $args
     * @return $this
     */
    protected function setBraintreeCustomerFromArgs(array $args = [])
    {
        if (3 === count($args)) {
            $this->customer($args[0], $args[1]);
        } elseif (null === $this->braintreeCustomer) {
            $this->customer($args[0]);
        }

        return $this;
    }

    /**
     * Get the Braintree customer instance for the current user and token.
     *
     * @param  string $token
     * @param  array $options
     * @return \Braintree\Customer
     * @throws \Laravel\Cashier\Gateway\Braintree\Exception
     */
    protected function getBraintreeCustomer($token, array $options = [])
    {
        if (isset($this->owner->payment_gateway) && 'braintree' !== $this->owner->payment_gateway) {
            throw new Exception('Customer is not using the Braintree payment gateway.');
        }

        return $this->getCustomerForGateway('braintree', $token, $options);
    }

    /**
     * Get the base subscription payload for Braintree.
     *
     * @param  \Braintree\Customer $customer
     * @param  array $options
     * @return array
     * @throws \Laravel\Cashier\Gateway\Braintree\Exception
     */
    protected function getSubscriptionPayload(Customer $customer, array $options = [])
    {
        $plan = Gateway::findPlan($this->plan);

        return array_merge([
            'planId' => $this->plan,
            'price' => (string) round($plan->price * (1 + ($this->owner->taxPercentage() / 100)), 2),
            'paymentMethodToken' => $customer->paymentMethods[0]->token,
            'trialPeriod' => $this->trialDays && ! $this->skipTrial ? true : false,
            'trialDurationUnit' => 'day',
            'trialDuration' => $this->trialDays && ! $this->skipTrial ? $this->trialDays : 0,
        ], $options);
    }

    /**
     * Add the coupon discount to the Braintree payload.
     *
     * @param  array $payload
     * @return array
     */
    protected function addCouponToPayload(array $payload)
    {
        if (! isset($payload['discounts']['add'])) {
            $payload['discounts']['add'] = [];
        }

        $payload['discounts']['add'][] = [
            'inheritedFromId' => $this->coupon,
        ];

        return $payload;
    }
}
