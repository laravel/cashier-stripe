<?php

namespace Laravel\Cashier;

use Illuminate\Support\Facades\View;
use Stripe\Checkout\Session;

class Checkout
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * @var \Stripe\Checkout\Session
     */
    protected $session;

    /**
     * Create a new Checkout instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\Checkout\Session  $session
     * @return void
     */
    public function __construct($owner, Session $session)
    {
        $this->owner = $owner;
        $this->session = $session;
    }

    /**
     * Get the View instance for the button.
     *
     * @param  array  $options
     * @return \Illuminate\Contracts\View\View
     */
    public function button(array $options = [])
    {
        return View::make('cashier::checkout', array_merge($options, [
            'stripeKey' => Cashier::stripeKey(),
            'sessionId' => $this->session->id,
        ]));
    }

    /**
     * @return \Stripe\Checkout\Session
     */
    public function asStripeCheckoutSession()
    {
        return $this->session;
    }
}
