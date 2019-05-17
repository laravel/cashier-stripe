<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Throwable;
use Laravel\Cashier\PaymentIntent;

class IncompletePayment extends Exception
{
    /**
     * The Stripe PaymentIntent object.
     *
     * @var \Laravel\Cashier\PaymentIntent
     */
    public $paymentIntent;

    public function __construct(PaymentIntent $paymentIntent, $message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->paymentIntent = $paymentIntent;
    }
}
