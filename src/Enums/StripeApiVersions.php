<?php

namespace Laravel\Cashier\Enums;

use Stripe\Coupon as StripeCoupon;
use Stripe\Discount as StripeDiscount;
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
     * Get `Stripe\Coupon` from `Stripe\Discount` for the current API.
     */
    public function couponFromDiscout(StripeDiscount $discount): StripeCoupon|string|null
    {
        return match (StripeApiVersion::CURRENT_MAJOR) {
            StripeApiVersions::BASIL => $discount->coupon,
            default => $discount->source->coupon,
        };
    }

    /**
     * Transform `ui_mode` for the current API.
     */
    public function transformUIMode(string $uiMode): string
    {
        return match ($this) {
            StripeApiVersions::DAHLIA => match ($uiMode) {
                'embedded' => 'embedded_page',
                'custom' => 'elements',
            },
            default => $uiMode,
        };
    }
}
