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
                            $e->payment->confirm([
                                'payment_method' => $paymentMethod instanceof StripePaymentMethod
                                    ? $paymentMethod->id
                                    : $paymentMethod,
                            ]);
                        } else {
                            $e->payment->confirm();
                        }
                    } catch (StripeCardException) {
                        //
                    }

                    $stripeSubscription = $subscription->asStripeSubscription(['latest_invoice.payment_intent']);

                    $subscription->fill([
                        'stripe_status' => $stripeSubscription->status,
                    ])->save();

                    if ($subscription->hasIncompletePayment()) {
                        (new Payment(
                            $stripeSubscription->latest_invoice->payment_intent
                        ))->validate();
                    }
                } else {
                    throw $e;
                }
            }
        }
    }
}
