<?php

namespace Laravel\Cashier\Concerns;

use Laravel\Cashier\Coupon;
use Laravel\Cashier\Exceptions\InvalidCoupon;

trait AllowsCoupons
{
    use InteractsWithStripe;

    /**
     * The coupon ID being applied.
     *
     * @var string|null
     */
    public ?string $couponId = null;

    /**
     * The promotion code ID being applied.
     *
     * @var string|null
     */
    public ?string $promotionCodeId = null;

    /**
     * Determines if user redeemable promotion codes are available in Stripe Checkout.
     *
     * @var bool
     */
    public bool $allowPromotionCodes = false;

    /**
     * The coupon IDs being applied.
     *
     * @var array|null
     */
    public ?array $coupons = null;

    /**
     * The promotion code IDs being applied.
     *
     * @var array|null
     */
    public ?array $promotionCodes = null;

    /**
     * Does the object allow multiple coupons
     *
     * @var bool
     */
    public bool $allowsMultipleCoupons = false;

    /**
     * The coupon ID to be applied.
     *
     * @param  string  $couponId
     * @return $this
     */
    public function withCoupon(string $couponId)
    {
        $this->couponId = $couponId;
        if ( $this->allowsMultipleCoupons ) {
            $this->coupons[] = $couponId;
        }

        return $this;
    }

    /**
     * The promotion code ID to apply.
     *
     * @param  string  $promotionCodeId
     * @return $this
     */
    public function withPromotionCode(string $promotionCodeId)
    {
        $this->promotionCodeId = $promotionCodeId;
        if ( $this->allowsMultipleCoupons ) {
            $this->promotionCodes[] = $promotionCodeId;
        }

        return $this;
    }

    /**
     * Enables user redeemable promotion codes for a Stripe Checkout session.
     *
     * @return $this
     */
    public function allowPromotionCodes()
    {
        $this->allowPromotionCodes = true;

        return $this;
    }

    /**
     * Return the discounts for a Stripe Checkout session.
     *
     * @return array[]|null
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCoupon
     */
    protected function checkoutDiscounts(): ?array
    {
        $discounts = [];

        if ($this->couponId) {
            $this->validateCouponForCheckout($this->couponId);

            $discounts[] = ['coupon' => $this->couponId];
        }

        if ($this->promotionCodeId) {
            $discounts[] = ['promotion_code' => $this->promotionCodeId];
        }

        return ! empty($discounts) ? $discounts : null;
    }

    /**
     * Validate that a coupon can be used in checkout sessions.
     *
     * @param  string  $couponId
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCoupon
     * @throws \Stripe\Exception\ApiErrorException
     */
    protected function validateCouponForCheckout(string $couponId): void
    {
        /** @var \Stripe\Service\CouponService $couponsService */
        $couponsService = static::stripe()->coupons;

        $stripeCoupon = $couponsService->retrieve($couponId);

        $coupon = new Coupon($stripeCoupon);

        if ($coupon->isForeverAmountOff()) {
            throw InvalidCoupon::cannotUseForeverAmountOffInCheckout($couponId);
        }
    }
}
