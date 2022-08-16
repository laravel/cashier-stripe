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
    public function handlePaymentFailure(Subscription $subscription, $paymentMethod = null)
    {
        if ($subscription->hasIncompletePayment()) {
            try {
                $subscription->latestPayment()->validate();
            } catch (IncompletePayment $e) {
                if ($e->payment->requiresConfirmation()) {
                    try {
                        if ($paymentMethod) {
                            $paymentIntent = $e->payment->confirm([
                                'expand' => ['invoice.subscription'],
                                'payment_method' => $paymentMethod instanceof StripePaymentMethod
                                    ? $paymentMethod->id
                                    : $paymentMethod,
                            ]);
                        } else {
                            $paymentIntent = $e->payment->confirm(['expand' => ['invoice.subscription']]);
                        }
                    } catch (StripeCardException) {
                        $paymentIntent = $e->payment->asStripePaymentIntent(['invoice.subscription']);
                    }

                    $subscription->fill([
                        'stripe_status' => $paymentIntent->invoice->subscription->status,
                    ])->save();

                    if ($subscription->hasIncompletePayment()) {
                        (new Payment($paymentIntent))->validate();
                    }
                } else {
                    throw $e;
                }
            }
        }
    }
}
