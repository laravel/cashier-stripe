<?php

namespace Laravel\Cashier\Concerns;

trait InteractsWithPaymentBehavior
{
    /**
     * Set the payment behavior for any subscription updates.
     *
     * @var string
     */
    protected $paymentBehavior = 'allow_incomplete';

    /**
     * Allow subscription changes even if payment fails.
     *
     * @return $this
     */
    public function allowPaymentFailures()
    {
        $this->paymentBehavior = 'allow_incomplete';

        return $this;
    }

    /**
     * Set any subscription change as pending until payment is successful.
     *
     * @return $this
     */
    public function pendingIfPaymentFails()
    {
        $this->paymentBehavior = 'pending_if_incomplete';

        return $this;
    }

    /**
     * Prevent any subscription change if payment is unsuccessful.
     *
     * @return $this
     */
    public function errorIfPaymentFails()
    {
        $this->paymentBehavior = 'error_if_incomplete';

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
