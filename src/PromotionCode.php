<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Stripe\PromotionCode as StripePromotionCode;

class PromotionCode implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Stripe PromotionCode instance.
     *
     * @var \Stripe\PromotionCode
     */
    protected $promotionCode;

    /**
     * Create a new PromotionCode instance.
     *
     * @param  \Stripe\PromotionCode  $promotionCode
     * @return void
     */
    public function __construct(StripePromotionCode $promotionCode)
    {
        $this->promotionCode = $promotionCode;
    }

    /**
     * Get the coupon that belongs to the promotion code.
     *
     * @return \Laravel\Cashier\Coupon
     */
    public function coupon()
    {
        return new Coupon($this->promotionCode->coupon);
    }

    /**
     * Get the Stripe PromotionCode instance.
     *
     * @return \Stripe\PromotionCode
     */
    public function asStripePromotionCode()
    {
        return $this->promotionCode;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripePromotionCode()->toArray();
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
        return $this->promotionCode->{$key};
    }
}
