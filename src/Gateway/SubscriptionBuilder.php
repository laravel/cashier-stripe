<?php

namespace Laravel\Cashier\Gateway;

use Carbon\Carbon;
use Illuminate\Support\Str;

abstract class SubscriptionBuilder
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
     * @var string
     */
    protected $plan;

    /**
     * The number of trial days to apply to the subscription.
     *
     * @var int|null
     */
    protected $trialDays;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The coupon code being applied to the customer.
     *
     * @var string|null
     */
    protected $coupon;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $name
     * @param  string  $plan
     */
    public function __construct($owner, $name, $plan)
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->plan = $plan;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays($trialDays)
    {
        $this->trialDays = $trialDays;

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
        $this->trialDays = $trialUntil->diffInDays();

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
     * Add a new subscription to the model.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Subscription
     */
    public function add(array $options = [])
    {
        return $this->create(null, $options);
    }

    /**
     * Create a new Stripe subscription.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Laravel\Cashier\Subscription
     */
    abstract public function create($token = null, array $options = []);

    /**
     * Get the customer ID for a given gateway.
     *
     * @param  string  $gateway
     * @return mixed
     */
    protected function getCustomerIdForGateway($gateway)
    {
        return $gateway === $this->owner->payment_gateway
            ? $this->owner->payment_gateway_id
            : $this->owner->getAttribute("{$gateway}_id");
    }

    /**
     * Get or create customer for specified gateway.
     *
     * @param  string  $gateway
     * @param  string  $token
     * @param  array  $options
     * @return mixed
     */
    protected function getCustomerForGateway($gateway, $token = null, array $options = [])
    {
        if (! $this->getCustomerIdForGateway($gateway)) {
            $createMethod = 'createAs'.Str::studly($gateway).'Customer';
            return method_exists($this->owner, $createMethod)
                ? $this->owner->$createMethod($token, $options)
                : $this->owner->createAsCustomer($token, $options);
        }

        $asMethod = 'as'.Str::studly($gateway).'Customer';
        $customer = method_exists($this->owner, $asMethod)
            ? $this->owner->$asMethod()
            : $this->owner->asCustomer($gateway);

        if ($token) {
            $this->owner->updateCard($token);
        }

        return $customer;
    }
}
