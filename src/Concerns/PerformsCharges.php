<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Checkout;
use Laravel\Cashier\Payment;
use Stripe\PaymentIntent as StripePaymentIntent;
use Stripe\Refund as StripeRefund;

trait PerformsCharges
{
    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  string  $paymentMethod
     * @param  array  $options
     * @return \Laravel\Cashier\Payment
     *
     * @throws \Laravel\Cashier\Exceptions\PaymentActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
     */
    public function charge($amount, $paymentMethod, array $options = [])
    {
        $options = array_merge([
            'confirmation_method' => 'automatic',
            'confirm' => true,
            'currency' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;
        $options['payment_method'] = $paymentMethod;

        if ($this->hasStripeId()) {
            $options['customer'] = $this->stripe_id;
        }

        $payment = new Payment(
            StripePaymentIntent::create($options, $this->stripeOptions())
        );

        $payment->validate();

        return $payment;
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
        return StripeRefund::create(
            ['payment_intent' => $paymentIntent] + $options,
            $this->stripeOptions()
        );
    }

    /**
     * Begin a new Checkout Session.
     *
     * @param  int  $amount
     * @param  string  $name
     * @param  int  $quantity
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function checkout($amount, $name, $quantity = 1, array $sessionOptions = [], array $customerOptions = [])
    {
        return Checkout::create($this, array_merge([
            'line_items' => [[
                'price_data' => [
                    'currency' => $this->preferredCurrency(),
                    'product_data' => [
                        'name' => $name,
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => $quantity,
            ]],
        ], $sessionOptions), $customerOptions);
    }

    /**
     * Begin a new Checkout Session.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function checkoutProduct($price, $quantity = 1, array $sessionOptions = [], array $customerOptions = [])
    {
        return Checkout::create($this, array_merge([
            'line_items' => [[
                'price' => $price,
                'quantity' => $quantity,
            ]],
        ], $sessionOptions), $customerOptions);
    }
}
