<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use JsonSerializable;
use Stripe\Checkout\Session;

class Checkout implements Arrayable, Jsonable, JsonSerializable, Responsable
{
    /**
     * Create a new checkout session instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  \Stripe\Checkout\Session  $session
     * @return void
     */
    public function __construct(
        protected $owner,
        protected Session $session
    ) {
        //
    }

    /**
     * Begin a new guest checkout session.
     *
     * @return \Laravel\Cashier\CheckoutBuilder
     */
    public static function guest(): CheckoutBuilder
    {
        return new CheckoutBuilder();
    }

    /**
     * Begin a new customer checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  object|null  $parentInstance
     * @return \Laravel\Cashier\CheckoutBuilder
     */
    public static function customer($owner, ?object $parentInstance = null): CheckoutBuilder
    {
        return new CheckoutBuilder($owner, $parentInstance);
    }

    /**
     * Begin a new checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\Cashier\Checkout
     */
    public static function create($owner, array $sessionOptions = [], array $customerOptions = []): Checkout
    {
        $data = array_merge([
            'mode' => Session::MODE_PAYMENT,
        ], $sessionOptions);

        if ($owner) {
            $data['customer'] = $owner->createOrGetStripeCustomer($customerOptions)->id;

            $stripe = $owner->stripe();
        } else {
            $stripe = Cashier::stripe();
        }

        // Make sure to collect address and name when Tax ID collection is enabled...
        if (isset($data['customer']) && ($data['tax_id_collection']['enabled'] ?? false)) {
            if (! isset($data['billing_address_collection'])) {
                $data['billing_address_collection'] = 'required';
            }

            $data['customer_update']['name'] = 'auto';
        }

        if ($data['mode'] === Session::MODE_PAYMENT && ($data['invoice_creation']['enabled'] ?? false)) {
            $data['invoice_creation']['invoice_data']['metadata']['is_on_session_checkout'] = true;
        } elseif ($data['mode'] === Session::MODE_SUBSCRIPTION) {
            $data['subscription_data']['metadata']['is_on_session_checkout'] = true;
        }

        // Remove success and cancel URLs if "ui_mode" is "embedded" or "custom"...
        if (isset($data['ui_mode']) && in_array($data['ui_mode'], ['embedded', 'custom'])) {
            $data['return_url'] = $sessionOptions['return_url'] ?? route('home');

            // Remove return URL for embedded UI mode when no redirection is desired on completion...
            if (isset($data['redirect_on_completion']) && $data['redirect_on_completion'] === 'never') {
                unset($data['return_url']);
            }
        } else {
            $data['success_url'] = $sessionOptions['success_url'] ?? route('home').'?checkout=success';
            $data['cancel_url'] = $sessionOptions['cancel_url'] ?? route('home').'?checkout=cancelled';
        }

        $session = $stripe->checkout->sessions->create($data);

        return new static($owner, $session);
    }

    /**
     * Redirect to the checkout session.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(): RedirectResponse
    {
        return Redirect::to($this->session->url, 303);
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return $this->redirect();
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
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripeCheckoutSession()->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Stripe object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->session->{$key};
    }
}
