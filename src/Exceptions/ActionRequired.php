<?php

namespace Laravel\Cashier\Exceptions;

use Laravel\Cashier\Payment;

class ActionRequired extends IncompletePayment
{
    /**
     * Create a new ActionRequired instance.
     *
     * @param  \Laravel\Cashier\Payment  $payment
     * @return self
     */
    public static function incomplete(Payment $payment)
    {
        return new self(
            $payment,
            'The payment attempt failed because it needs an extra action before it can be completed.'
        );
    }
}
