<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Checkout;
use Laravel\Cashier\Payment;
use Stripe\PaymentIntent as StripePaymentIntent;
use Stripe\Refund as StripeRefund;

trait PerformsCharges
{
    /**
     * Determines if user redeemable promotion codes are available in Stripe Checkout.
     *
     * @var bool
     */
    protected $allowPromotionCodes = false;

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
     * Begin a new checkout session for existing prices.
     *
     * @param  array|string  $items
     * @param  int  $quantity
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function checkout($items, array $sessionOptions = [], array $customerOptions = [])
    {
        $items = collect((array) $items)->map(function ($item, $key) {
            if (is_string($key)) {
                return ['price' => $key, 'quantity' => $item];
            }

            $item = is_string($item) ? ['price' => $item] : $item;

            $item['quantity'] = $item['quantity'] ?? 1;

            return $item;
        })->values()->all();

        return Checkout::create($this, array_merge([
            'allow_promotion_codes' => $this->allowPromotionCodes,
            'line_items' => $items,
        ], $sessionOptions), $customerOptions);
    }

    /**
     * Begin a new checkout session for a "one-off" charge.
     *
     * @param  int  $amount
     * @param  string  $name
     * @param  int  $quantity
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function checkoutCharge($amount, $name, $quantity = 1, array $sessionOptions = [], array $customerOptions = [])
    {
        return $this->checkout([[
            'price_data' => [
                'currency' => $this->preferredCurrency(),
                'product_data' => [
                    'name' => $name,
                ],
                'unit_amount' => $amount,
            ],
            'quantity' => $quantity,
        ]], $sessionOptions, $customerOptions);
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
}
