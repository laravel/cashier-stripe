<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Str;

class DiscountTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $priceId;

    /**
     * @var string
     */
    protected static $couponId;

    /**
     * @var string
     */
    protected static $secondCouponId;

    /**
     * @var string
     */
    protected static $promotionCodeId;

    /**
     * @var string
     */
    protected static $promotionCodeCode;

    public static function setUpBeforeClass(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

        parent::setUpBeforeClass();

        static::$productId = self::stripe()->products->create([
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ])->id;

        static::$priceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1000,
        ])->id;

        static::$couponId = self::stripe()->coupons->create([
            'duration' => 'repeating',
            'amount_off' => 500,
            'duration_in_months' => 3,
            'currency' => 'USD',
        ])->id;

        static::$secondCouponId = self::stripe()->coupons->create([
            'duration' => 'once',
            'percent_off' => 20,
            'currency' => 'USD',
        ])->id;

        static::$promotionCodeId = self::stripe()->promotionCodes->create([
            'coupon' => static::$secondCouponId,
            'code' => static::$promotionCodeCode = Str::random(16),
        ])->id;
    }

    public function test_applying_discounts_to_existing_customers()
    {
        $user = $this->createCustomer('applying_coupons_to_existing_customers');

        $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $user->applyCoupon(static::$couponId);

        $this->assertEquals(static::$couponId, $user->discount()->coupon()->id);

        $user->applyPromotionCode(static::$promotionCodeId);

        $this->assertEquals(static::$secondCouponId, $user->discount()->coupon()->id);
        $this->assertEquals(static::$promotionCodeId, $user->discount()->promotionCode()->id);
        $this->assertEquals(static::$secondCouponId, $user->discount()->promotionCode()->coupon()->id);
        $this->assertEquals(static::$promotionCodeCode, $user->discount()->promotionCode()->code);
    }

    public function test_applying_discounts_to_existing_subscriptions()
    {
        $user = $this->createCustomer('applying_coupons_to_existing_subscriptions');

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $subscription->applyCoupon(static::$couponId);

        $this->assertEquals(static::$couponId, $subscription->discount()->coupon()->id);

        $subscription->applyPromotionCode(static::$promotionCodeId);

        $this->assertEquals(static::$secondCouponId, $subscription->discount()->coupon()->id);
        $this->assertEquals(static::$promotionCodeId, $subscription->discount()->promotionCode()->id);
        $this->assertEquals(static::$secondCouponId, $subscription->discount()->promotionCode()->coupon()->id);
        $this->assertEquals(static::$promotionCodeCode, $subscription->discount()->promotionCode()->code);
    }

    public function test_customers_can_retrieve_a_promotion_code()
    {
        $user = $this->createCustomer('customers_can_retrieve_a_promotion_code');

        $promotionCode = $user->findPromotionCode(static::$promotionCodeCode);

        $this->assertEquals(static::$promotionCodeCode, $promotionCode->code);

        // Inactive promotion codes aren't retrieved with the "active only" method...
        $inactivePromotionCode = $user->stripe()->promotionCodes->create([
            'active' => false,
            'coupon' => static::$couponId,
            'code' => 'NEWYEAR',
        ]);

        $promotionCode = $user->findActivePromotionCode($inactivePromotionCode->id);

        $this->assertNull($promotionCode);
    }
}
