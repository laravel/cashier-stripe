<?php

namespace Laravel\Cashier\Concerns;

use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Payment;
use LogicException;

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
            $this->stripe()->paymentIntents->create($options)
        );
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
        return $this->stripe()->refunds->create(
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
        $payload = array_filter([
            'allow_promotion_codes' => $this->allowPromotionCodes,
            'automatic_tax' => $this->automaticTaxPayload(),
            'discounts' => $this->checkoutDiscounts(),
            'line_items' => Collection::make((array) $items)->map(function ($item, $key) {
                if (is_string($key)) {
                    return ['price' => $key, 'quantity' => $item];
                }

                $item = is_string($item) ? ['price' => $item] : $item;

                $item['quantity'] = $item['quantity'] ?? 1;

                return $item;
            })->values()->all(),
            'tax_id_collection' => [
                'enabled' => Cashier::$calculatesTaxes ?: $this->collectTaxIds,
            ],
        ]);

        return Checkout::create($this, array_merge($payload, $sessionOptions), $customerOptions);
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
        if ($this->isAutomaticTaxEnabled()) {
            throw new LogicException('For now, you cannot use checkout charges in combination automatic tax calculation.');
        }

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
}
