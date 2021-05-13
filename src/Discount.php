<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Stripe\Discount as StripeDiscount;

class Discount implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Stripe PaymentIntent instance.
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
     * Get the coupon code applied to the discount.
     *
     * @return string
     */
    public function coupon()
    {
        return $this->discount->coupon->id;
    }

    /**
     * Get the coupon name applied to the discount.
     *
     * @return string
     */
    public function couponName()
    {
        return $this->discount->coupon->name ?: $this->discount->coupon->id;
    }

    /**
     * Determine if the discount is a percentage.
     *
     * @return bool
     */
    public function discountIsPercentage()
    {
        return ! is_null($this->discount->coupon->percent_off);
    }

    /**
     * Get the discount percentage for the invoice.
     *
     * @return float|null
     */
    public function percentOff()
    {
        return $this->discount->coupon->percent_off;
    }

    /**
     * Get the amount off for the discount.
     *
     * @return string|null
     */
    public function amountOff()
    {
        if (! is_null($this->discount->coupon->amount_off)) {
            return $this->formatAmount($this->rawAmountOff());
        }
    }

    /**
     * Get the raw amount off for the discount.
     *
     * @return int|null
     */
    public function rawAmountOff()
    {
        return $this->discount->coupon->amount_off;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->discount->coupon->currency);
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
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Stripe Discount.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->discount->{$key};
    }
}
