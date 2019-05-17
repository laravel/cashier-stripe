<?php

namespace Laravel\Cashier;

use Stripe\PaymentIntent as StripePaymentIntent;

class PaymentIntent
{
    /**
     * The Stripe PaymentIntent instance.
     *
     * @var \Stripe\PaymentIntent
     */
    protected $paymentIntent;

    /**
     * Create a new PaymentIntent instance.
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
     * The Stripe PaymentIntent ID.
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
     * The Stripe PaymentIntent instance.
     *
     * @return \Stripe\PaymentIntent
     */
    public function asStripePaymentIntent()
    {
        return $this->paymentIntent;
    }
}
