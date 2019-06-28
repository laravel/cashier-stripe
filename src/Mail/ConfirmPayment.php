<?php

namespace Laravel\Cashier\Mail;

use Laravel\Cashier\Payment;
use Illuminate\Mail\Mailable;

class ConfirmPayment extends Mailable
{
    /**
     * The Stripe model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $owner;

    /**
     * @var \Laravel\Cashier\Payment
     */
    public $payment;

    /**
     * Create a new ConfirmPayment instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Laravel\Cashier\Payment $payment
     * @return void
     */
    public function __construct($owner, Payment $payment)
    {
        $this->owner = $owner;
        $this->payment = $payment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject(__('Confirm Payment'))
            ->markdown('cashier::emails.confirm_payment');
    }
}
