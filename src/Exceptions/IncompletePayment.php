<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Laravel\Cashier\Payment;
use Throwable;

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
     * @param  string  $message
     * @param  int  $code
     * @param  \Throwable|null  $previous
     * @return void
     */
    public function __construct(Payment $payment, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->payment = $payment;
    }

    /**
     * Create a new IncompletePayment instance with a `payment_action_required` type.
     *
     * @param  \Laravel\Cashier\Payment  $payment
     * @return static
     */
    public static function paymentMethodRequired(Payment $payment)
    {
        return new static(
            $payment,
            'The payment attempt failed because of an invalid payment method.'
        );
    }

    /**
     * Create a new IncompletePayment instance with a `requires_action` type.
     *
     * @param  \Laravel\Cashier\Payment  $payment
     * @return static
     */
    public static function requiresAction(Payment $payment)
    {
        return new static(
            $payment,
            'The payment attempt failed because additional action is required before it can be completed.'
        );
    }

    /**
     * Create a new IncompletePayment instance with a `requires_confirmation` type.
     *
     * @param  \Laravel\Cashier\Payment  $payment
     * @return static
     */
    public static function requiresConfirmation(Payment $payment)
    {
        return new static(
            $payment,
            'The payment attempt failed because it needs to be confirmed before it can be completed.'
        );
    }
}
