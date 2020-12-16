<?php

namespace Laravel\Cashier\Tests\Feature;

use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Exceptions\PaymentActionRequired;
use Laravel\Cashier\Exceptions\PaymentFailure;
use Laravel\Cashier\Payment;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;
use Stripe\Coupon;
use Stripe\Invoice;
use Stripe\Price;
use Stripe\Product;
use Stripe\Subscription as StripeSubscription;
use Stripe\TaxRate;

class MeteredBillingTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $planId;

    /**
     * @var string
     */
    protected static $licensedPlanId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$productId = static::$stripePrefix.'product-1'.Str::random(10);

        Product::create([
            'id' => static::$productId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ]);

        static::$planId = Price::create([
            'id' => static::$planId,
            'nickname' => 'Monthly Metered $1 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered'
            ],
            'unit_amount' => 100,
            'product' => static::$productId,
        ])->id;

        static::$licensedPlanId = Price::create([
            'id' => static::$licensedPlanId,
            'nickname' => 'Monthly $10 Licensed',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month'
            ],
            'unit_amount' => 1000,
            'product' => static::$productId,
        ])->id;

    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Price(static::$planId));
        static::deleteStripeResource(new Price(static::$licensedPlanId));
        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_null_quantity_items_can_be_created()
    {
        $user = $this->createCustomer('test_null_quantity_items_can_be_created');

        $user->newSubscription('main', static::$planId)
            ->quantity(null, static::$planId)
            ->create('pm_card_visa');

        $this->assertTrue($user->subscribed('main'));
    }
}
