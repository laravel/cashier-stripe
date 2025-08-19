<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Laravel\Cashier\Exceptions\InvalidPaymentMethod;
use LogicException;
use Stripe\PaymentMethod as StripePaymentMethod;

class PaymentMethod implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Create a new PaymentMethod instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\PaymentMethod  $paymentMethod
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidPaymentMethod
     */
    public function __construct(protected $owner, protected StripePaymentMethod $paymentMethod)
    {
        if (is_null($paymentMethod->customer)) {
            throw new LogicException('The payment method is not attached to a customer.');
        }

        if ($owner->stripe_id !== $paymentMethod->customer) {
            throw InvalidPaymentMethod::invalidOwner($paymentMethod, $owner);
        }
    }

    /**
     * Delete the payment method.
     *
     * @return void
     */
    public function delete(): void
    {
        $this->owner->deletePaymentMethod($this->paymentMethod);
    }

    /**
     * Get the Stripe model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the Stripe PaymentMethod instance.
     *
     * @return \Stripe\PaymentMethod
     */
    public function asStripePaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripePaymentMethod()->toArray();
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
        return $this->paymentMethod->{$key};
    }
}
