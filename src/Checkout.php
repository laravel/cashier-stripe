<?php

namespace Laravel\Cashier;

use Illuminate\Support\Facades\View;
use Stripe\Checkout\Session;

class Checkout
{
    /**
     * The Stripe model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Stripe checkout session instance.
     *
     * @var \Stripe\Checkout\Session
     */
    protected $session;

    /**
     * Create a new checkout session instance.
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
     * Begin a new checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public static function create($owner, array $sessionOptions = [], array $customerOptions = [])
    {
        $customer = $owner->createOrGetStripeCustomer($customerOptions);

        $session = Session::create(array_merge([
            'customer' => $customer->id,
            'mode' => 'payment',
            'success_url' => $sessionOptions['success_url'] ?? route('home').'?checkout=success',
            'cancel_url' => $sessionOptions['cancel_url'] ?? route('home').'?checkout=cancelled',
            'payment_method_types' => ['card'],
        ], $sessionOptions), Cashier::stripeOptions());

        return new static($customer, $session);
    }

    /**
     * Get the view instance for the button.
     *
     * @param  string  $label
     * @param  array  $options
     * @return \Illuminate\Contracts\View\View
     */
    public function button($label = 'Check out', array $options = [])
    {
        return View::make('cashier::checkout', array_merge([
            'label' => $label,
            'sessionId' => $this->session->id,
            'stripeKey' => config('cashier.key'),
        ], $options));
    }

    /**
     * Get the Checkout Session as a Stripe Checkout Session object.
     *
     * @return \Stripe\Checkout\Session
     */
    public function asStripeCheckoutSession()
    {
        return $this->session;
    }

    /**
     * Dynamically get values from the Stripe Checkout Session.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->session->{$key};
    }
}
