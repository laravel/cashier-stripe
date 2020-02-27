<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Exceptions\InvalidPaymentMethod;
use Stripe\PaymentMethod as StripePaymentMethod;

class PaymentMethod
{
    /**
     * The Stripe model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Stripe PaymentMethod instance.
     *
     * @var \Stripe\PaymentMethod
     */
    protected $paymentMethod;

    /**
     * Create a new PaymentMethod instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\PaymentMethod  $paymentMethod
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidPaymentMethod
     */
    public function __construct($owner, StripePaymentMethod $paymentMethod)
    {
        if ($owner->stripe_id !== $paymentMethod->customer) {
            throw InvalidPaymentMethod::invalidOwner($paymentMethod, $owner);
        }

        $this->owner = $owner;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Delete the payment method.
     *
     * @return \Stripe\PaymentMethod
     */
    public function delete()
    {
        return $this->owner->removePaymentMethod($this->paymentMethod);
    }

    /**
     * Get the Stripe model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the Stripe PaymentMethod instance.
     *
     * @return \Stripe\PaymentMethod
     */
    public function asStripePaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * Dynamically get values from the Stripe PaymentMethod.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->paymentMethod->{$key};
    }
}
