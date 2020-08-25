<?php

namespace Laravel\Cashier\Concerns;

use Stripe\Subscription as StripeSubscription;

trait InteractsWithPaymentBehavior
{
    /**
     * Set the payment behavior for any subscription updates.
     *
     * @var string
     */
    protected $paymentBehavior = StripeSubscription::PAYMENT_BEHAVIOR_ALLOW_INCOMPLETE;

    /**
     * Allow subscription changes even if payment fails.
     *
     * @return $this
     */
    public function allowPaymentFailures()
    {
        $this->paymentBehavior = StripeSubscription::PAYMENT_BEHAVIOR_ALLOW_INCOMPLETE;

        return $this;
    }

    /**
     * Set any subscription change as pending until payment is successful.
     *
     * @return $this
     */
    public function pendingIfPaymentFails()
    {
        $this->paymentBehavior = StripeSubscription::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE;

        return $this;
    }

    /**
     * Prevent any subscription change if payment is unsuccessful.
     *
     * @return $this
     */
    public function errorIfPaymentFails()
    {
        $this->paymentBehavior = StripeSubscription::PAYMENT_BEHAVIOR_ERROR_IF_INCOMPLETE;

        return $this;
    }

    /**
     * Determine the payment behavior when updating the subscription.
     *
     * @return string
     */
    public function paymentBehavior()
    {
        return $this->paymentBehavior;
    }
}
