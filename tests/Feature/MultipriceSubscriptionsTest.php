<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Laravel\Cashier\Tests\Fixtures\User;
use Stripe\Price;
use Stripe\Product;
use Stripe\Subscription;
use Stripe\TaxRate;

class MultipriceSubscriptionsTest extends FeatureTestCase
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
    protected static $otherPriceId;

    /**
     * @var string
     */
    protected static $premiumPriceId;

    /**
     * @var string
     */
    protected static $taxRateId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$productId = Product::create([
            'id' => static::$productId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ])->id;

        static::$priceId = Price::create([
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1000,
            'product' => static::$productId,
        ])->id;

        static::$otherPriceId = Price::create([
            'nickname' => 'Monthly $10 Other',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 1000,
            'product' => static::$productId,
        ])->id;

        static::$premiumPriceId = Price::create([
            'nickname' => 'Monthly $20 Premium',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 2000,
            'product' => static::$productId,
        ])->id;

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

        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_customers_can_have_multiprice_subscriptions()
    {
        $user = $this->createCustomer('customers_can_have_multiprice_subscriptions');

        $user->priceTaxRates = [self::$otherPriceId => [self::$taxRateId]];

        $subscription = $user->newSubscription('main', [self::$priceId, self::$otherPriceId])
            ->price(self::$premiumPriceId, 5)
            ->quantity(10, self::$priceId)
            ->create('pm_card_visa');

        $this->assertTrue($user->subscribed('main', self::$priceId));
        $this->assertTrue($user->onPrice(self::$priceId));

        $item = $subscription->findItemOrFail(self::$priceId);
        $otherItem = $subscription->findItemOrFail(self::$otherPriceId);
        $premiumItem = $subscription->findItemOrFail(self::$premiumPriceId);

        $this->assertCount(3, $subscription->items);
        $this->assertSame(self::$priceId, $item->stripe_price);
        $this->assertSame(10, $item->quantity);
        $this->assertSame(self::$otherPriceId, $otherItem->stripe_price);
        $this->assertSame(1, $otherItem->quantity);
        $this->assertSame(self::$taxRateId, Arr::first($otherItem->asStripeSubscriptionItem()->tax_rates)->id);
        $this->assertSame(self::$premiumPriceId, $premiumItem->stripe_price);
        $this->assertSame(5, $premiumItem->quantity);
    }

    public function test_customers_can_add_prices()
    {
        $user = $this->createCustomer('customers_can_add_prices');

        $subscription = $user->newSubscription('main', self::$priceId)->create('pm_card_visa');

        $subscription->addPrice(self::$otherPriceId, 5);

        $this->assertTrue($user->onPrice(self::$priceId));
        $this->assertFalse($user->onPrice(self::$premiumPriceId));

        $item = $subscription->findItemOrFail(self::$priceId);
        $otherItem = $subscription->findItemOrFail(self::$otherPriceId);

        $this->assertCount(2, $subscription->items);
        $this->assertSame(self::$priceId, $item->stripe_price);
        $this->assertSame(1, $item->quantity);
        $this->assertSame(self::$otherPriceId, $otherItem->stripe_price);
        $this->assertSame(5, $otherItem->quantity);
    }

    public function test_customers_can_remove_prices()
    {
        $user = $this->createCustomer('customers_can_remove_prices');

        $subscription = $user->newSubscription('main', [self::$priceId, self::$otherPriceId])->create('pm_card_visa');

        $this->assertCount(2, $subscription->items);

        $subscription->removePrice(self::$priceId);

        $this->assertCount(1, $subscription->items);
    }

    public function test_customers_cannot_remove_the_last_price()
    {
        $user = $this->createCustomer('customers_cannot_remove_the_last_price');

        $subscription = $this->createSubscriptionWithSinglePrice($user);

        $this->expectException(SubscriptionUpdateFailure::class);

        $subscription->removePrice(self::$priceId);
    }

    public function test_multiprice_subscriptions_can_be_resumed()
    {
        $user = $this->createCustomer('multiprice_subscriptions_can_be_resumed');

        $subscription = $user->newSubscription('main', [self::$priceId, self::$otherPriceId])->create('pm_card_visa');

        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());

        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
    }

    public function test_price_is_required_when_updating_quantities_for_multiprice_subscriptions()
    {
        $user = $this->createCustomer('price_is_required_when_updating_quantities_for_multiprice_subscriptions');

        $subscription = $this->createSubscriptionWithMultiplePrices($user);

        $this->expectException(InvalidArgumentException::class);

        $subscription->updateQuantity(5);
    }

    public function test_subscription_item_quantities_can_be_updated()
    {
        $user = $this->createCustomer('subscription_item_quantities_can_be_updated');

        $subscription = $user->newSubscription('main', [self::$priceId, self::$otherPriceId])->create('pm_card_visa');

        $subscription->updateQuantity(5, self::$otherPriceId);

        $item = $subscription->findItemOrFail(self::$otherPriceId);

        $this->assertSame(5, $item->quantity);
    }

    public function test_subscription_item_quantities_can_be_incremented()
    {
        $user = $this->createCustomer('subscription_item_quantities_can_be_updated');

        $subscription = $user->newSubscription('main', [self::$priceId, self::$otherPriceId])->create('pm_card_visa');

        $subscription->incrementQuantity(3, self::$otherPriceId);

        $item = $subscription->findItemOrFail(self::$otherPriceId);

        $this->assertSame(4, $item->quantity);

        $item->incrementQuantity(3);

        $this->assertSame(7, $item->quantity);
    }

    public function test_subscription_item_quantities_can_be_decremented()
    {
        $user = $this->createCustomer('subscription_item_quantities_can_be_updated');

        $subscription = $user->newSubscription('main', [self::$priceId, self::$otherPriceId])
            ->quantity(5, self::$otherPriceId)
            ->create('pm_card_visa');

        $subscription->decrementQuantity(2, self::$otherPriceId);

        $item = $subscription->findItemOrFail(self::$otherPriceId);

        $this->assertSame(3, $item->quantity);

        $item->decrementQuantity(2);

        $this->assertSame(1, $item->quantity);
    }

    public function test_multiple_prices_can_be_swapped()
    {
        $user = $this->createCustomer('multiple_prices_can_be_swapped');

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $subscription = $subscription->swap([static::$otherPriceId, static::$premiumPriceId => ['quantity' => 3]]);

        $prices = $subscription->items()->pluck('stripe_price');

        $this->assertCount(2, $prices);
        $this->assertContains(self::$otherPriceId, $prices);
        $this->assertContains(self::$premiumPriceId, $prices);

        $premiumPrice = $subscription->findItemOrFail(static::$premiumPriceId);

        $this->assertEquals(3, $premiumPrice->quantity);
    }

    public function test_subscription_items_can_swap_prices()
    {
        $user = $this->createCustomer('subscription_items_can_swap_prices');

        $subscription = $user->newSubscription('main', static::$priceId)->create('pm_card_visa');

        $item = $subscription->items()->first()->swap(static::$otherPriceId, ['quantity' => 3]);

        $subscription->refresh();

        $this->assertCount(1, $subscription->items);
        $this->assertSame(self::$otherPriceId, $subscription->stripe_price);
        $this->assertSame(self::$otherPriceId, $item->stripe_price);
        $this->assertSame(3, $item->quantity);
    }

    public function test_subscription_item_changes_can_be_prorated()
    {
        $user = $this->createCustomer('subscription_item_changes_can_be_prorated');

        $subscription = $user->newSubscription('main', static::$premiumPriceId)->create('pm_card_visa');

        $this->assertEquals(2000, ($invoice = $user->invoices()->first())->rawTotal());

        $subscription->noProrate()->addPrice(self::$otherPriceId);

        // Assert that no new invoice was created because of no prorating.
        $this->assertEquals($invoice->id, $user->invoices()->first()->id);

        $subscription->addPriceAndInvoice(self::$priceId);

        // Assert that a new invoice was created because of no prorating.
        $this->assertEquals(1000, ($invoice = $user->invoices()->first())->rawTotal());
        $this->assertEquals(4000, $user->upcomingInvoice()->rawTotal());

        $subscription->noProrate()->removePrice(self::$premiumPriceId);

        // Assert that no new invoice was created because of no prorating.
        $this->assertEquals($invoice->id, $user->invoices()->first()->id);
        $this->assertEquals(2000, $user->upcomingInvoice()->rawTotal());
    }

    public function test_subscription_item_quantity_changes_can_be_prorated()
    {
        $user = $this->createCustomer('subscription_item_quantity_changes_can_be_prorated');

        $subscription = $user->newSubscription('main', [self::$priceId, self::$otherPriceId])
            ->quantity(3, self::$otherPriceId)
            ->create('pm_card_visa');

        $this->assertEquals(4000, ($invoice = $user->invoices()->first())->rawTotal());

        $subscription->noProrate()->updateQuantity(1, self::$otherPriceId);

        $this->assertEquals(2000, $user->upcomingInvoice()->rawTotal());
    }

    /**
     * Create a subscription with a single price.
     *
     * @param  \Laravel\Cashier\Tests\Fixtures\User  $user
     * @return \Laravel\Cashier\Subscription
     */
    protected function createSubscriptionWithSinglePrice(User $user)
    {
        $subscription = $user->subscriptions()->create([
            'name' => 'main',
            'stripe_id' => 'sub_foo',
            'stripe_price' => self::$priceId,
            'quantity' => 1,
            'stripe_status' => Subscription::STATUS_ACTIVE,
        ]);

        $subscription->items()->create([
            'stripe_id' => 'it_foo',
            'stripe_price' => self::$priceId,
            'quantity' => 1,
        ]);

        return $subscription;
    }

    /**
     * Create a subscription with multiple prices.
     *
     * @param  \Laravel\Cashier\Tests\Fixtures\User  $user
     * @return \Laravel\Cashier\Subscription
     */
    protected function createSubscriptionWithMultiplePrices(User $user)
    {
        $subscription = $this->createSubscriptionWithSinglePrice($user);

        $subscription->stripe_price = null;
        $subscription->quantity = null;
        $subscription->save();

        $subscription->items()->create([
            'stripe_id' => 'it_foo',
            'stripe_price' => self::$otherPriceId,
            'quantity' => 1,
        ]);

        return $subscription;
    }
}
