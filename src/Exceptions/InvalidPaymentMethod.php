<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Stripe\PaymentMethod as StripePaymentMethod;

class InvalidPaymentMethod extends Exception
{
    /**
     * Create a new InvalidPaymentMethod instance.
     *
     * @param  \Stripe\PaymentMethod  $paymentMethod
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function invalidOwner(StripePaymentMethod $paymentMethod, $owner)
    {
        return new static(
            "The payment method `{$paymentMethod->id}`'s customer `{$paymentMethod->customer}` does not belong to this customer `$owner->stripe_id`."
        );
    }
}
