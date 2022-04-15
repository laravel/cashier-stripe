<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Stripe\Coupon as StripeCoupon;

class Coupon implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Stripe Coupon instance.
     *
     * @var \Stripe\Coupon
     */
    protected $coupon;

    /**
     * Create a new Coupon instance.
     *
     * @param  \Stripe\Coupon  $coupon
     * @return void
     */
    public function __construct(StripeCoupon $coupon)
    {
        $this->coupon = $coupon;
    }

    /**
     * Get the readable name for the Coupon.
     *
     * @return string
     */
    public function name()
    {
        return $this->coupon->name ?: $this->coupon->id;
    }

    /**
     * Determine if the coupon is a percentage.
     *
     * @return bool
     */
    public function isPercentage()
    {
        return ! is_null($this->coupon->percent_off);
    }

    /**
     * Get the discount percentage for the invoice.
     *
     * @return float|null
     */
    public function percentOff()
    {
        return $this->coupon->percent_off;
    }

    /**
     * Get the amount off for the coupon.
     *
     * @return string|null
     */
    public function amountOff()
    {
        if (! is_null($this->coupon->amount_off)) {
            return $this->formatAmount($this->rawAmountOff());
        }
    }

    /**
     * Get the raw amount off for the coupon.
     *
     * @return int|null
     */
    public function rawAmountOff()
    {
        return $this->coupon->amount_off;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->coupon->currency);
    }

    /**
     * Get the Stripe Coupon instance.
     *
     * @return \Stripe\Coupon
     */
    public function asStripeCoupon()
    {
        return $this->coupon;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripeCoupon()->toArray();
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
        return $this->coupon->{$key};
    }
}
