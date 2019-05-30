<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Throwable;
use Laravel\Cashier\Payment;

class IncompletePayment extends Exception
{
    /**
     * The Cashier Payment object.
     *
     * @var \Laravel\Cashier\Payment
     */
    public $payment;

    /**
     * Create a new IncompletePayment instance.
     *
     * @param  \Laravel\Cashier\Payment  $payment
     * @return void
     */
    public function __construct(Payment $payment, $message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->payment = $payment;
    }
}
