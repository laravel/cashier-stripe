<?php

namespace Laravel\Cashier\Exceptions;

use Laravel\Cashier\PaymentIntent;

class PaymentFailure extends IncompletePayment
{
    public static function cardError(PaymentIntent $paymentIntent)
    {
        return new static(
            $paymentIntent,
            'The payment attempt failed because there was a card error.'
        );
    }
}
