<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Exceptions\ActionRequired;
use Laravel\Cashier\Exceptions\PaymentFailure;
use Stripe\PaymentIntent as StripePaymentIntent;

class Payment
{
    /**
     * The Stripe PaymentIntent instance.
     *
     * @var \Stripe\PaymentIntent
     */
    protected $paymentIntent;

    /**
     * Create a new Payment instance.
     *
     * @param  \Stripe\PaymentIntent  $paymentIntent
     * @return void
     */
    public function __construct(StripePaymentIntent $paymentIntent)
    {
        $this->paymentIntent = $paymentIntent;
    }

    /**
     * The Stripe PaymentIntent ID.
     *
     * @return string
     */
    public function id()
    {
        return $this->paymentIntent->id;
    }

    /**
     * The Stripe PaymentIntent client secret.
     *
     * @return string
     */
    public function clientSecret()
    {
        return $this->paymentIntent->client_secret;
    }

    /**
     * Determine if the payment needs a valid payment method.
     *
     * @return bool
     */
    public function requiresPaymentMethod()
    {
        return $this->paymentIntent->status === 'requires_payment_method';
    }

    /**
     * Determine if the payment needs an extra action like 3D Secure.
     *
     * @return bool
     */
    public function requiresAction()
    {
        return $this->paymentIntent->status === 'requires_action';
    }

    /**
     * Determine if the payment was cancelled.
     *
     * @return bool
     */
    public function isCancelled()
    {
        return $this->paymentIntent->status === 'cancelled';
    }

    /**
     * Determine if the payment was successful.
     *
     * @return bool
     */
    public function isSucceeded()
    {
        return $this->paymentIntent->status === 'succeeded';
    }

    /**
     * Validate if the payment intent was successful and throw an exception if not.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\ActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
     */
    public function validate()
    {
        if ($this->requiresPaymentMethod()) {
            throw PaymentFailure::cardError($this);
        } elseif ($this->requiresAction()) {
            throw ActionRequired::incomplete($this);
        }
    }

    /**
     * The Stripe PaymentIntent instance.
     *
     * @return \Stripe\PaymentIntent
     */
    public function asStripePaymentIntent()
    {
        return $this->paymentIntent;
    }
}
