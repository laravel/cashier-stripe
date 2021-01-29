<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Str;
use Laravel\Cashier\SubscriptionUsage;
use Stripe\Exception\InvalidRequestException;
use Stripe\Plan;
use Stripe\Price;
use Stripe\Product;

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
    protected static $secondPlanId;

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
            'nickname' => 'Monthly Metered $1 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
            ],
            'unit_amount' => 100,
            'product' => static::$productId,
        ])->id;

        static::$secondPlanId = Price::create([
            'nickname' => 'Monthly Metered $2 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
            ],
            'unit_amount' => 200,
            'product' => static::$productId,
        ])->id;

        static::$licensedPlanId = Price::create([
            'nickname' => 'Monthly $10 Licensed',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'unit_amount' => 1000,
            'product' => static::$productId,
        ])->id;
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Plan(static::$planId));
        static::deleteStripeResource(new Plan(static::$secondPlanId));
        static::deleteStripeResource(new Plan(static::$licensedPlanId));
        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_null_quantity_items_can_be_created()
    {
        $user = $this->createCustomer('test_null_quantity_items_can_be_created');

        $subscription = $user->newSubscription('main', static::$planId)
            ->quantity(null, static::$planId)
            ->create('pm_card_visa');

        $this->assertTrue($user->subscribed('main'));

        $subscription->cancel();
    }

    public function test_usage_report_with_single_item_subscription()
    {
        $user = $this->createCustomer('test_usage_report_with_single_item_subscription');

        $subscription = $user->newSubscription('main', static::$planId)
            ->quantity(null, static::$planId)
            ->create('pm_card_visa');

        $subscription->incrementUsage();
        sleep(1);
        $subscription->incrementUsage(10);

        sleep(1);
        $subscription->incrementUsage(10, static::$planId);

        $summary = $subscription->items->first()->getStripeUsageRecordSummary()->first();

        $this->assertSame($summary->total_usage, 21);
    }

    public function test_usage_report_with_licensed_subscription()
    {
        $user = $this->createCustomer('test_usage_report_with_licensed_subscription');

        $subscription = $user->newSubscription('main', static::$licensedPlanId)
            ->create('pm_card_visa');

        try {
            $subscription->incrementUsage();
        } catch (\Exception $e) {
            $this->assertInstanceOf(InvalidRequestException::class, $e);
        }

        $subscription->swap([
            static::$planId => [
                'quantity' => null,
            ],
        ]);

        sleep(1);
        $subscription->incrementUsage();
        $summary = $subscription->items->first()->getStripeUsageRecordSummary()->first();
        $this->assertSame($summary->total_usage, 1);
    }

    public function test_usage_report_with_multiplan()
    {
        $user = $this->createCustomer('test_usage_report_with_multiplan');

        $subscription = $user->newSubscription('main', static::$planId)
            ->quantity(null, static::$planId)
            ->create('pm_card_visa');

        $subscription->addPlan(static::$secondPlanId, null);

        $this->assertSame($subscription->items->count(), 2);

        $subscription->incrementUsage(20, static::$secondPlanId);

        $summary = $subscription->findItemOrFail(static::$secondPlanId)->getStripeUsageRecordSummary()->first();
        $this->assertSame($summary->total_usage, 20);
        $subscription->removePlan(static::$secondPlanId);

        $this->assertSame($subscription->items->count(), 1);

        $secondSub = $user->newSubscription('test_swap', static::$planId)
            ->quantity(null, static::$planId)
            ->create('pm_card_visa');

        $secondSub->swap([
            static::$planId => [
                'quantity' => null
            ],
            static::$secondPlanId => [
                'quantity' => null,
            ],
        ]);

        $this->assertSame($secondSub->items->count(), 2);

        $secondSub->incrementUsage(10, static::$secondPlanId);
        $summary = $secondSub->findItemOrFail(static::$secondPlanId)->getStripeUsageRecordSummary()->first();
        $this->assertSame($summary->total_usage, 10);

        $subItem = $secondSub->findItemOrFail(static::$planId);
        $subItem->incrementUsage(14);
        $summary = $subItem->getStripeUsageRecordSummary()->first();
        $this->assertSame($summary->total_usage, 14);

    }
}
