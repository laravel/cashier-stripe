<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Stripe\Discount as StripeDiscount;

class Discount implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Stripe Discount instance.
     *
     * @var \Stripe\Discount
     */
    protected $discount;

    /**
     * Create a new Discount instance.
     *
     * @param  \Stripe\Discount  $discount
     * @return void
     */
    public function __construct(StripeDiscount $discount)
    {
        $this->discount = $discount;
    }

    /**
     * Get the coupon applied to the discount.
     *
     * @return \Laravel\Cashier\Coupon
     */
    public function coupon()
    {
        return new Coupon($this->discount->coupon);
    }

    /**
     * Get the Stripe Discount instance.
     *
     * @return \Stripe\Discount
     */
    public function asStripeDiscount()
    {
        return $this->discount;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripeDiscount()->toArray();
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
    public function __get($key)
    {
        return $this->discount->{$key};
    }
}
