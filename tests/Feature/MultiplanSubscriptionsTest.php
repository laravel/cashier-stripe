<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Laravel\Cashier\Tests\Fixtures\User;
use Stripe\Plan;
use Stripe\Product;
use Stripe\TaxRate;

class MultiplanSubscriptionsTest extends FeatureTestCase
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
    protected static $otherPlanId;

    /**
     * @var string
     */
    protected static $premiumPlanId;

    /**
     * @var string
     */
    protected static $taxRateId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$productId = static::$stripePrefix.'product-1'.Str::random(10);
        static::$planId = static::$stripePrefix.'monthly-10-'.Str::random(10);
        static::$otherPlanId = static::$stripePrefix.'monthly-10-'.Str::random(10);
        static::$premiumPlanId = static::$stripePrefix.'monthly-20-premium-'.Str::random(10);

        Product::create([
            'id' => static::$productId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ]);

        Plan::create([
            'id' => static::$planId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 1000,
            'product' => static::$productId,
        ]);

        Plan::create([
            'id' => static::$otherPlanId,
            'nickname' => 'Monthly $10 Other',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 1000,
            'product' => static::$productId,
        ]);

        Plan::create([
            'id' => static::$premiumPlanId,
            'nickname' => 'Monthly $20 Premium',
            'currency' => 'USD',
            'interval' => 'month',
            'billing_scheme' => 'per_unit',
            'amount' => 2000,
            'product' => static::$productId,
        ]);

        static::$taxRateId = TaxRate::create([
            'display_name' => 'VAT',
            'description' => 'VAT Belgium',
            'jurisdiction' => 'BE',
            'percentage' => 21,
            'inclusive' => false,
        ])->id;
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        static::deleteStripeResource(new Plan(static::$planId));
        static::deleteStripeResource(new Plan(static::$otherPlanId));
        static::deleteStripeResource(new Plan(static::$premiumPlanId));
        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_customers_can_have_multiplan_subscriptions()
    {
        $user = $this->createCustomer('customers_can_have_multiplan_subscriptions');

        $user->planTaxRates = [self::$otherPlanId => [self::$taxRateId]];

        $subscription = $user->newSubscription('main', [self::$planId, self::$otherPlanId])
            ->plan(self::$premiumPlanId, 5)
            ->quantity(10, self::$planId)
            ->create('pm_card_visa');

        $this->assertTrue($user->subscribed('main', self::$planId));
        $this->assertTrue($user->onPlan(self::$planId));

        $item = $subscription->findItemOrFail(self::$planId);
        $otherItem = $subscription->findItemOrFail(self::$otherPlanId);
        $premiumItem = $subscription->findItemOrFail(self::$premiumPlanId);

        $this->assertCount(3, $subscription->items);
        $this->assertSame(self::$planId, $item->stripe_plan);
        $this->assertSame(10, $item->quantity);
        $this->assertSame(self::$otherPlanId, $otherItem->stripe_plan);
        $this->assertSame(1, $otherItem->quantity);
        $this->assertSame(self::$taxRateId, Arr::first($otherItem->asStripeSubscriptionItem()->tax_rates)->id);
        $this->assertSame(self::$premiumPlanId, $premiumItem->stripe_plan);
        $this->assertSame(5, $premiumItem->quantity);
    }

    public function test_customers_can_add_plans()
    {
        $user = $this->createCustomer('customers_can_add_plans');

        $subscription = $user->newSubscription('main', self::$planId)->create('pm_card_visa');

        $subscription->addPlan(self::$otherPlanId, 5);

        $this->assertTrue($user->onPlan(self::$planId));
        $this->assertFalse($user->onPlan(self::$premiumPlanId));

        $item = $subscription->findItemOrFail(self::$planId);
        $otherItem = $subscription->findItemOrFail(self::$otherPlanId);

        $this->assertCount(2, $subscription->items);
        $this->assertSame(self::$planId, $item->stripe_plan);
        $this->assertSame(1, $item->quantity);
        $this->assertSame(self::$otherPlanId, $otherItem->stripe_plan);
        $this->assertSame(5, $otherItem->quantity);
    }

    public function test_customers_can_remove_plans()
    {
        $user = $this->createCustomer('customers_can_remove_plans');

        $subscription = $user->newSubscription('main', [self::$planId, self::$otherPlanId])->create('pm_card_visa');

        $this->assertCount(2, $subscription->items);

        $subscription->removePlan(self::$planId);

        $this->assertCount(1, $subscription->items);
    }

    public function test_customers_cannot_remove_the_last_plan()
    {
        $user = $this->createCustomer('customers_cannot_remove_the_last_plan');

        $subscription = $this->createSubscriptionWithSinglePlan($user);

        $this->expectException(SubscriptionUpdateFailure::class);

        $subscription->removePlan(self::$planId);
    }

    public function test_plan_is_required_when_updating_quantities_for_multiplan_subscriptions()
    {
        $user = $this->createCustomer('plan_is_required_when_updating_quantities_for_multiplan_subscriptions');

        $subscription = $this->createSubscriptionWithMultiplePlans($user);

        $this->expectException(InvalidArgumentException::class);

        $subscription->updateQuantity(5);
    }

    public function test_subscription_item_quantities_can_be_updated()
    {
        $user = $this->createCustomer('subscription_item_quantities_can_be_updated');

        $subscription = $user->newSubscription('main', [self::$planId, self::$otherPlanId])->create('pm_card_visa');

        $subscription->updateQuantity(5, self::$otherPlanId);

        $item = $subscription->findItemOrFail(self::$otherPlanId);

        $this->assertSame(5, $item->quantity);
    }

    public function test_subscription_item_quantities_can_be_incremented()
    {
        $user = $this->createCustomer('subscription_item_quantities_can_be_updated');

        $subscription = $user->newSubscription('main', [self::$planId, self::$otherPlanId])->create('pm_card_visa');

        $subscription->incrementQuantity(3, self::$otherPlanId);

        $item = $subscription->findItemOrFail(self::$otherPlanId);

        $this->assertSame(4, $item->quantity);

        $item->incrementQuantity(3);

        $this->assertSame(7, $item->quantity);
    }

    public function test_subscription_item_quantities_can_be_decremented()
    {
        $user = $this->createCustomer('subscription_item_quantities_can_be_updated');

        $subscription = $user->newSubscription('main', [self::$planId, self::$otherPlanId])
            ->quantity(5, self::$otherPlanId)
            ->create('pm_card_visa');

        $subscription->decrementQuantity(2, self::$otherPlanId);

        $item = $subscription->findItemOrFail(self::$otherPlanId);

        $this->assertSame(3, $item->quantity);

        $item->decrementQuantity(2);

        $this->assertSame(1, $item->quantity);
    }

    public function test_multiple_plans_can_be_swapped()
    {
        $user = $this->createCustomer('multiple_plans_can_be_swapped');

        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        $subscription = $subscription->swap([static::$otherPlanId, static::$premiumPlanId => ['quantity' => 3]]);

        $plans = $subscription->items()->pluck('stripe_plan');

        $this->assertCount(2, $plans);
        $this->assertContains(self::$otherPlanId, $plans);
        $this->assertContains(self::$premiumPlanId, $plans);

        $premiumPlan = $subscription->findItemOrFail(static::$premiumPlanId);

        $this->assertEquals(3, $premiumPlan->quantity);
    }

    public function test_subscription_items_can_swap_plans()
    {
        $user = $this->createCustomer('subscription_items_can_swap_plans');

        $subscription = $user->newSubscription('main', static::$planId)->create('pm_card_visa');

        $item = $subscription->items()->first()->swap(static::$otherPlanId, ['quantity' => 3]);

        $subscription->refresh();

        $this->assertCount(1, $subscription->items);
        $this->assertSame(self::$otherPlanId, $subscription->stripe_plan);
        $this->assertSame(self::$otherPlanId, $item->stripe_plan);
        $this->assertSame(3, $item->quantity);
    }

    public function test_subscription_item_changes_can_be_prorated()
    {
        $user = $this->createCustomer('subscription_item_changes_can_be_prorated');

        $subscription = $user->newSubscription('main', static::$premiumPlanId)->create('pm_card_visa');

        $this->assertEquals(2000, ($invoice = $user->invoices()->first())->rawTotal());

        $subscription->noProrate()->addPlanAndInvoice(self::$otherPlanId);

        // Assert that no new invoice was created because of no prorating.
        $this->assertEquals($invoice->id, $user->invoices()->first()->id);

        $subscription->prorate()->addPlanAndInvoice(self::$planId);

        // Assert that a new invoice was created because of no prorating.
        $this->assertEquals(1000, ($invoice = $user->invoices()->first())->rawTotal());

        $subscription->noProrate()->removePlan(self::$premiumPlanId);

        // Assert that no new invoice was created because of no prorating.
        $this->assertEquals($invoice->id, $user->invoices()->first()->id);
    }

    public function test_subscription_item_quantity_changes_can_be_prorated()
    {
        $user = $this->createCustomer('subscription_item_quantity_changes_can_be_prorated');

        $subscription = $user->newSubscription('main', [self::$planId, self::$otherPlanId])
            ->quantity(3, self::$otherPlanId)
            ->create('pm_card_visa');

        $this->assertEquals(4000, ($invoice = $user->invoices()->first())->rawTotal());

        $subscription->noProrate()->updateQuantity(1, self::$otherPlanId);

        $this->assertEquals(2000, $user->upcomingInvoice()->rawTotal());
    }

    /**
     * Create a subscription with a single plan.
     *
     * @param  \Laravel\Cashier\Tests\Fixtures\User  $user
     * @return \Laravel\Cashier\Subscription
     */
    protected function createSubscriptionWithSinglePlan(User $user)
    {
        $subscription = $user->subscriptions()->create([
            'name' => 'main',
            'stripe_id' => 'sub_foo',
            'stripe_plan' => self::$planId,
            'quantity' => 1,
            'stripe_status' => 'active',
        ]);

        $subscription->items()->create([
            'stripe_id' => 'it_foo',
            'stripe_plan' => self::$planId,
            'quantity' => 1,
        ]);

        return $subscription;
    }

    /**
     * Create a subscription with multiple plans.
     *
     * @param  \Laravel\Cashier\Tests\Fixtures\User  $user
     * @return \Laravel\Cashier\Subscription
     */
    protected function createSubscriptionWithMultiplePlans(User $user)
    {
        $subscription = $this->createSubscriptionWithSinglePlan($user);

        $subscription->stripe_plan = null;
        $subscription->quantity = null;
        $subscription->save();

        $subscription->items()->create([
            'stripe_id' => 'it_foo',
            'stripe_plan' => self::$otherPlanId,
            'quantity' => 1,
        ]);

        return $subscription;
    }
}
