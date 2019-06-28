<?php

namespace Laravel\Cashier\Exceptions;

use Laravel\Cashier\Payment;

class PaymentFailure extends IncompletePayment
{
    /**
     * Create a new PaymentFailure instance.
     *
     * @param  \Laravel\Cashier\Payment  $payment
     * @return self
     */
    public static function cardError(Payment $payment)
    {
        return new self(
            $payment,
            'The payment attempt failed because of a card error.'
        );
    }
}
