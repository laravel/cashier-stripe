<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use JsonSerializable;
use Laravel\Cashier\Concerns\InteractsWithStripe;
use Laravel\Cashier\Enums\StripeApiVersions;
use Stripe\Coupon as StripeCoupon;
use Stripe\PromotionCode as StripePromotionCode;

class PromotionCode implements Arrayable, Jsonable, JsonSerializable
{
    use InteractsWithStripe;

    /**
     * Create a new PromotionCode instance.
     *
     * @param  \Stripe\PromotionCode  $promotionCode
     * @return void
     */
    public function __construct(protected StripePromotionCode $promotionCode)
    {
        //
    }

    /**
     * Get the coupon that belongs to the promotion code.
     *
     * @return \Laravel\Cashier\Coupon
     *
     * @throws \InvalidArgumentException
     */
    public function coupon(): Coupon
    {
        $coupon = StripeApiVersions::current()->couponFromPromotionCode($this->promotionCode);

        if (is_null($coupon)) {
            throw new InvalidArgumentException('Unable to retrieve coupon information for the promotion code.');
        }

        if (! $coupon instanceof StripeCoupon) {
            $coupon = static::stripe()->coupons->retrieve($coupon);
        }

        return new Coupon($coupon);
    }

    /**
     * Get the Stripe PromotionCode instance.
     *
     * @return \Stripe\PromotionCode
     */
    public function asStripePromotionCode(): StripePromotionCode
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
    public function __get(string $key)
    {
        return $this->promotionCode->{$key};
    }
}
