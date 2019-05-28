<?php

namespace Laravel\Cashier\Exceptions;

use Laravel\Cashier\PaymentIntent;

class ActionRequired extends IncompletePayment
{
    public static function incomplete(PaymentIntent $paymentIntent)
    {
        return new self(
            $paymentIntent,
            'The payment attempt failed because it needs an extra action before it can be completed.'
        );
    }
}
