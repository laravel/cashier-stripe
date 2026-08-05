<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Str;
use Stripe\Util\ApiVersion as StripeApiVersion;

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

        $payload = match (StripeApiVersion::CURRENT_MAJOR) {
            'basil' => [
                'coupon' => static::$secondCouponId,
                'code' => static::$promotionCodeCode = Str::random(16),
            ],
            default => [
                'promotion' => [
                    'type' => 'coupon',
                    'coupon' => static::$secondCouponId,
                ],
                'code' => static::$promotionCodeCode = Str::random(16),
            ],
        };

        static::$promotionCodeId = self::stripe()->promotionCodes->create($payload)->id;
    }

    public function test_applying_discounts_to_existing_customers()
    {
        $user = $this->createCustomer('applying_coupons_to_existing_customers');

        // Create main subscription (will be the primary/default)
        $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        // Apply coupon to primary subscription only (default behavior)
        $user->applyCoupon(static::$couponId);

        $this->assertEquals(static::$couponId, $user->discount()->coupon()->id);

        // Apply promotion code to primary subscription only (default behavior)
        $user->applyPromotionCode(static::$promotionCodeId);

        $this->assertEquals(static::$secondCouponId, $user->discount()->coupon()->id);
        $this->assertEquals(static::$promotionCodeId, $user->discount()->promotionCode()->id);
        $this->assertEquals(static::$secondCouponId, $user->discount()->promotionCode()->coupon()->id);
        $this->assertEquals(static::$promotionCodeCode, $user->discount()->promotionCode()->code);
    }

    public function test_applying_discounts_to_specific_subscription_types()
    {
        $user = $this->createCustomer('applying_coupons_to_specific_subscriptions');

        // Create multiple subscription types
        $mainSubscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');
        $premiumSubscription = $user->newSubscription('premium', static::$priceId)->create('pm_card_visa');

        // Apply coupon to specific subscription type
        $user->applyCoupon(static::$couponId, 'premium');

        // Main subscription should not have discount
        $this->assertNull($mainSubscription->fresh()->discount());

        // Premium subscription should have discount
        $this->assertEquals(static::$couponId, $premiumSubscription->fresh()->discount()->coupon()->id);

        // Apply promotion code to multiple specific subscription types
        $user->applyPromotionCode(static::$promotionCodeId, ['main', 'premium']);

        // Both subscriptions should now have the promotion code
        $this->assertEquals(static::$secondCouponId, $mainSubscription->fresh()->discount()->coupon()->id);
        $this->assertEquals(static::$secondCouponId, $premiumSubscription->fresh()->discount()->coupon()->id);
    }

    public function test_applying_discounts_to_all_subscriptions()
    {
        $user = $this->createCustomer('applying_coupons_to_all_subscriptions');

        // Create multiple subscription types
        $mainSubscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');
        $premiumSubscription = $user->newSubscription('premium', static::$priceId)->create('pm_card_visa');

        // Apply coupon to all subscriptions using explicit method
        $user->applyCouponToAllSubscriptions(static::$couponId);

        // Both subscriptions should have the discount
        $this->assertEquals(static::$couponId, $mainSubscription->fresh()->discount()->coupon()->id);
        $this->assertEquals(static::$couponId, $premiumSubscription->fresh()->discount()->coupon()->id);

        // Apply promotion code to all subscriptions using wildcard
        $user->applyPromotionCode(static::$promotionCodeId, '*');

        // Both subscriptions should now have the promotion code
        $this->assertEquals(static::$secondCouponId, $mainSubscription->fresh()->discount()->coupon()->id);
        $this->assertEquals(static::$secondCouponId, $premiumSubscription->fresh()->discount()->coupon()->id);
    }

    public function test_applying_discounts_directly_to_subscriptions()
    {
        $user = $this->createCustomer('applying_coupons_to_subscription_directly');

        // Create subscription
        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        // Apply coupon directly to subscription (existing method)
        $subscription->applyCoupon(static::$couponId);

        $this->assertEquals(static::$couponId, $subscription->discount()->coupon()->id);

        // Apply promotion code directly to subscription (existing method)
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
        $inactivePromotionCode = $user->stripe()->promotionCodes->create(array_merge([
            'active' => false,
            'code' => 'NEWYEAR',
        ], match (StripeApiVersion::CURRENT_MAJOR) {
            'basil' => ['coupon' => static::$couponId],
            default => ['promotion' => ['type' => 'coupon', 'coupon' => static::$couponId]],
        }));

        $promotionCode = $user->findActivePromotionCode($inactivePromotionCode->id);

        $this->assertNull($promotionCode);
    }
}
