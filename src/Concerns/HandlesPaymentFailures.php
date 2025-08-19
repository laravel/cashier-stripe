<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Payment;
use Laravel\Cashier\Subscription;
use Stripe\Exception\CardException as StripeCardException;
use Stripe\PaymentMethod as StripePaymentMethod;

trait HandlesPaymentFailures
{
    /**
     * Indicates if incomplete payments should be confirmed automatically.
     *
     * @var bool
     */
    protected bool $confirmIncompletePayment = true;

    /**
     * The options to be used when confirming a payment intent.
     *
     * @var array
     */
    protected array $paymentConfirmationOptions = [];

    /**
     * Handle a failed payment for the given subscription.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @param  \Stripe\PaymentMethod|string|null  $paymentMethod
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     *
     * @internal
     */
    public function handlePaymentFailure(Subscription $subscription, $paymentMethod = null): void
    {
        if ($this->confirmIncompletePayment && $subscription->hasIncompletePayment()) {
            try {
                $subscription->latestPayment()->validate();
            } catch (IncompletePayment $e) {
                if ($e->payment->requiresConfirmation()) {
                    try {
                        if ($paymentMethod) {
                            $paymentIntent = $e->payment->confirm(array_merge(
                                $this->paymentConfirmationOptions,
                                [
                                    'payment_method' => $paymentMethod instanceof StripePaymentMethod
                                        ? $paymentMethod->id
                                        : $paymentMethod,
                                ]
                            ));
                        } else {
                            $paymentIntent = $e->payment->confirm($this->paymentConfirmationOptions);
                        }
                    } catch (StripeCardException) {
                        $paymentIntent = $e->payment->asStripePaymentIntent();
                    }

                    // Since the invoice field is no longer available on payment intent, we need to refresh the subscription directly...
                    $stripeSubscription = $subscription->asStripeSubscription();

                    $subscription->fill([
                        'stripe_status' => $stripeSubscription->status,
                    ])->save();

                    if ($subscription->hasIncompletePayment()) {
                        (new Payment($paymentIntent))->validate();
                    }
                } else {
                    throw $e;
                }
            }
        }

        $this->confirmIncompletePayment = true;
        $this->paymentConfirmationOptions = [];
    }

    /**
     * Prevent automatic confirmation of incomplete payments.
     *
     * @return $this
     */
    public function ignoreIncompletePayments()
    {
        $this->confirmIncompletePayment = false;

        return $this;
    }

    /**
     * Specify the options to be used when confirming a payment intent.
     *
     * @param  array  $options
     * @return $this
     */
    public function withPaymentConfirmationOptions(array $options)
    {
        $this->paymentConfirmationOptions = $options;

        return $this;
    }
}
