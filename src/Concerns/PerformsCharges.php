<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Checkout;
use Laravel\Cashier\Payment;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

trait PerformsCharges
{
    use AllowsCoupons;
    use InteractsWithStripe;

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function charge(int $amount, string $paymentMethod, array $options = []): Payment
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
     */
    public function pay(int $amount, array $options = []): Payment
    {
        $options['automatic_payment_methods'] = ['enabled' => true];

        unset($options['payment_method_types']);

        return $this->createPayment($amount, $options);
    }

    /**
     * Create a new PaymentIntent instance for the given payment method types.
     */
    public function payWith(int $amount, array $paymentMethods, array $options = []): Payment
    {
        $options['payment_method_types'] = $paymentMethods;

        unset($options['automatic_payment_methods']);

        return $this->createPayment($amount, $options);
    }

    /**
     * Create a new Payment instance with a Stripe PaymentIntent.
     */
    public function createPayment(int $amount, array $options = []): Payment
    {
        $options = array_merge([
            'currency' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;

        if ($this->hasStripeId()) {
            $options['customer'] = $this->stripe_id;
        }

        /** @var \Stripe\Service\PaymentIntentService $paymentIntentsService */
        $paymentIntentsService = static::stripe()->paymentIntents;

        return new Payment(
            $paymentIntentsService->create($options)
        );
    }

    /**
     * Find a payment intent by ID.
     */
    public function findPayment(string $id): ?Payment
    {
        $stripePaymentIntent = null;

        /** @var \Stripe\Service\PaymentIntentService $paymentIntentsService */
        $paymentIntentsService = static::stripe()->paymentIntents;

        try {
            $stripePaymentIntent = $paymentIntentsService->retrieve($id);
        } catch (StripeInvalidRequestException $exception) {
            //
        }

        return $stripePaymentIntent ? new Payment($stripePaymentIntent) : null;
    }

    /**
     * Refund a customer for a charge.
     *
     * @return \Stripe\Refund
     */
    public function refund(string $paymentIntent, array $options = [])
    {
        /** @var \Stripe\Service\RefundService $refundsService */
        $refundsService = static::stripe()->refunds;

        return $refundsService->create(
            ['payment_intent' => $paymentIntent] + $options
        );
    }

    /**
     * Begin a new checkout session for existing prices.
     */
    public function checkout(string|array $items, array $sessionOptions = [], array $customerOptions = []): Checkout
    {
        return Checkout::customer($this, $this)->create($items, $sessionOptions, $customerOptions);
    }

    /**
     * Begin a new checkout session for a "one-off" charge.
     */
    public function checkoutCharge(
        int $amount,
        string $name,
        int $quantity = 1,
        array $sessionOptions = [],
        array $customerOptions = [],
        array $productData = []
    ): Checkout {
        return $this->checkout([[
            'price_data' => [
                'currency' => $this->preferredCurrency(),
                'product_data' => array_merge($productData, [
                    'name' => $name,
                ]),
                'unit_amount_decimal' => $amount,
            ],
            'quantity' => $quantity,
        ]], $sessionOptions, $customerOptions);
    }
}
