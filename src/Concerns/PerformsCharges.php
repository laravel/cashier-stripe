<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Checkout;
use Laravel\Cashier\Payment;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

trait PerformsCharges
{
    use AllowsCoupons;

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  string  $paymentMethod
     * @param  array  $options
     * @return \Laravel\Cashier\Payment
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function charge($amount, $paymentMethod, array $options = [])
    {
        $options = array_merge([
            'confirmation_method' => 'automatic',
            'confirm' => true,
        ], $options);

        $options['payment_method'] = $paymentMethod;

        $payment = $this->createPayment($amount, $options);

        $payment->validate();

        return $payment;
    }

    /**
     * Create a new PaymentIntent instance.
     *
     * @param  int  $amount
     * @param  array  $options
     * @return \Laravel\Cashier\Payment
     */
    public function pay($amount, array $options = [])
    {
        $options['automatic_payment_methods'] = ['enabled' => true];

        unset($options['payment_method_types']);

        return $this->createPayment($amount, $options);
    }

    /**
     * Create a new PaymentIntent instance for the given payment method types.
     *
     * @param  int  $amount
     * @param  array  $paymentMethods
     * @param  array  $options
     * @return \Laravel\Cashier\Payment
     */
    public function payWith($amount, array $paymentMethods, array $options = [])
    {
        $options['payment_method_types'] = $paymentMethods;

        unset($options['automatic_payment_methods']);

        return $this->createPayment($amount, $options);
    }

    /**
     * Create a new Payment instance with a Stripe PaymentIntent.
     *
     * @param  int  $amount
     * @param  array  $options
     * @return \Laravel\Cashier\Payment
     */
    public function createPayment($amount, array $options = [])
    {
        $options = array_merge([
            'currency' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;

        if ($this->hasStripeId()) {
            $options['customer'] = $this->stripe_id;
        }

        return new Payment(
            static::stripe()->paymentIntents->create($options)
        );
    }

    /**
     * Find a payment intent by ID.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Payment|null
     */
    public function findPayment($id)
    {
        $stripePaymentIntent = null;

        try {
            $stripePaymentIntent = static::stripe()->paymentIntents->retrieve($id);
        } catch (StripeInvalidRequestException $exception) {
            //
        }

        return $stripePaymentIntent ? new Payment($stripePaymentIntent) : null;
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $paymentIntent
     * @param  array  $options
     * @return \Stripe\Refund
     */
    public function refund($paymentIntent, array $options = [])
    {
        return static::stripe()->refunds->create(
            ['payment_intent' => $paymentIntent] + $options
        );
    }

    /**
     * Begin a new checkout session for existing prices.
     *
     * @param  array|string  $items
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function checkout($items, array $sessionOptions = [], array $customerOptions = [])
    {
        return Checkout::customer($this, $this)->create($items, $sessionOptions, $customerOptions);
    }

    /**
     * Begin a new checkout session for a "one-off" charge.
     *
     * @param  int  $amount
     * @param  string  $name
     * @param  int  $quantity
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @param  array  $productData
     * @return \Laravel\Cashier\Checkout
     */
    public function checkoutCharge($amount, $name, $quantity = 1, array $sessionOptions = [], array $customerOptions = [], array $productData = [])
    {
        return $this->checkout([[
            'price_data' => [
                'currency' => $this->preferredCurrency(),
                'product_data' => array_merge($productData, [
                    'name' => $name,
                ]),
                'unit_amount' => $amount,
            ],
            'quantity' => $quantity,
        ]], $sessionOptions, $customerOptions);
    }
}
