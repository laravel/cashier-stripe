<?php

namespace Laravel\Cashier\Exceptions;

use Exception;

class StripeSecretKeyException extends Exception
{
    /**
     * Create a new StripeSecretKeyException instance.
     *
     * @param  string  $expectedEnvironment
     * @return static
     */
    public static function invalidEnvironment($expectedEnvironment)
    {
        return new static(
            "The provided Stripe API key does not match the expected environment \"$expectedEnvironment\""
        );
    }
}
