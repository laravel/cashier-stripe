<?php

namespace Laravel\Cashier\Exceptions;

use Exception;

class InvalidCoupon extends Exception
{
    /**
     * Create a new InvalidCoupon instance.
     *
     * @param  string  $couponId
     * @return static
     */
    public static function foreverAmountOffCouponNotAllowed(string $couponId)
    {
        return new static("Coupon [{$couponId}] with amount_off and forever duration cannot be applied to subscriptions. Apply the coupon directly to invoices instead.");
    }

    /**
     * Create a new InvalidCoupon instance for subscription context.
     *
     * @param  string  $couponId
     * @return static
     */
    public static function cannotApplyForeverAmountOffToSubscription(string $couponId)
    {
        return new static("Coupon [{$couponId}] cannot be applied to subscriptions because it has amount_off with forever duration. Use invoice-level discounts instead.");
    }

    /**
     * Create a new InvalidCoupon instance for checkout context.
     *
     * @param  string  $couponId
     * @return static
     */
    public static function cannotUseForeverAmountOffInCheckout(string $couponId)
    {
        return new static("Coupon [{$couponId}] with amount_off and forever duration cannot be used in checkout sessions. Consider using time-limited coupons instead.");
    }
}
