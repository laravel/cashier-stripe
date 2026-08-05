<?php

namespace Laravel\Cashier\Enums;

use Stripe\Coupon as StripeCoupon;
use Stripe\Discount as StripeDiscount;
use Stripe\PromotionCode as StripePromotionCode;
use Stripe\Util\ApiVersion;

enum StripeApiVersions: string
{
    case DAHLIA = 'dahlia';
    case CLOVER = 'clover';
    case BASIL = 'basil';

    /**
     * Resolve current version.
     *
     * @return static
     */
    public static function current()
    {
        return self::tryFrom(ApiVersion::CURRENT_MAJOR);
    }

    /**
     * Get coupon information from `Stripe\Discount` for the current API.
     */
    public function couponFromDiscount(StripeDiscount $discount): StripeCoupon|string|null
    {
        return match ($this) {
            StripeApiVersions::BASIL => $discount->coupon,
            StripeApiVersions::CLOVER => $discount->source->coupon,
            StripeApiVersions::DAHLIA => $discount->source->coupon,
        };
    }

    /**
     * Get coupon information from `Stripe\PromotionCode` for the current API.
     */
    public function couponFromPromotionCode(StripePromotionCode $promotionCode): StripeCoupon|string|null
    {
        return match ($this) {
            StripeApiVersions::BASIL => $promotionCode->coupon,
            StripeApiVersions::CLOVER => $promotionCode->promotion->coupon,
            StripeApiVersions::DAHLIA => $promotionCode->promotion->coupon,
        };
    }

    /**
     * Transform `ui_mode` for the current API.
     */
    public function transformUiMode(string $uiMode): string
    {
        return match ($this) {
            StripeApiVersions::BASIL => $uiMode,
            StripeApiVersions::CLOVER => $uiMode,
            StripeApiVersions::DAHLIA => match ($uiMode) {
                'embedded' => 'embedded_page',
                'custom' => 'elements',
            },
        };
    }
}
