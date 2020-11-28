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
     * Begin a new Checkout Session for an existing Price ID.
     *
     * @param  mixed  $items
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public function checkout($items, array $sessionOptions = [], array $customerOptions = [])
    {
        return Checkout::create($this, array_merge([
            'allow_promotion_codes' => $this->allowPromotionCodes,
            'line_items' => $this->transformLineItems($items),
        ], $sessionOptions), $customerOptions);
    }

    /**
     * Method to transform the line items to a multi-dimensional array
     * as required by Stripe.
     * $items can be the following:
     *  - String representing 1 price (we will assume quantity = 1)
     *  - An array with just 1 price ['price' => 'price_xx', quantity => 1]
     *  - Multi dimensional array with multiple prices [['price' => 'price_xx', quantity => 1], [...]]
     *
     * @param mixed $items
     * @return array
     */
    private function transformLineItems($items)
    {
        if (is_string($items)) {
            return [[
                'price' => $items,
                'quantity' => 1,
            ]];
        }

        // If $items is one dimension i.e. ['price' => 'price_xxx', 'quantity' => 1]
        if (count($items) === count($items, COUNT_RECURSIVE)) {
            return $this->ensureItemsHasQuantity([$items]);
        }

        // $items is multi-dimentions i.e. [['price' => 'price_xxx', 'quantity' => 1], [...]]
        return $this->ensureItemsHasQuantity($items);
    }

    /**
     * Add quantity to the items array if it doesn't exist
     *
     * @param array $items
     * @return array
     */
    private function ensureItemsHasQuantity($items)
    {
        return collect($items)->transform(function ($item) {
            return array_key_exists('quantity', $item) ? $item : array_merge($item, ['quantity' => 1]);
        })->toArray();
    }

    /**
     * Begin a new Checkout Session for a "one-off" charge.
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
        return Checkout::create($this, array_merge([
            'allow_promotion_codes' => $this->allowPromotionCodes,
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
