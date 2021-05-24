<?php

namespace Laravel\Cashier\Tests\Feature;

use Exception;
use InvalidArgumentException;
use Stripe\Exception\InvalidRequestException;
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
    protected static $meteredPrice;

    /**
     * @var string
     */
    protected static $otherMeteredPrice;

    /**
     * @var string
     */
    protected static $licensedPrice;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$productId = Product::create([
            'id' => static::$productId,
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ])->id;

        static::$meteredPrice = Price::create([
            'nickname' => 'Monthly Metered $1 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
            ],
            'unit_amount' => 100,
            'product' => static::$productId,
        ])->id;

        static::$otherMeteredPrice = Price::create([
            'nickname' => 'Monthly Metered $2 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
            ],
            'unit_amount' => 200,
            'product' => static::$productId,
        ])->id;

        static::$licensedPrice = Price::create([
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

        static::deleteStripeResource(new Product(static::$productId));
    }

    public function test_report_usage_for_metered_price()
    {
        $user = $this->createCustomer('report_usage_for_metered_price');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        $subscription->reportUsage(5);

        $subscription->reportUsageFor(static::$meteredPrice, 10);

        $summary = $subscription->usageRecords()->first();

        $this->assertSame($summary->total_usage, 15);
    }

    public function test_reporting_usage_for_licensed_price_throws_exception()
    {
        $user = $this->createCustomer('reporting_usage_for_licensed_price_throws_exception');

        $subscription = $user->newSubscription('main', static::$licensedPrice)->create('pm_card_visa');

        try {
            $subscription->reportUsage();
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidRequestException::class, $e);
        }
    }

    public function test_reporting_usage_for_multiprice_subscriptions()
    {
        $user = $this->createCustomer('reporting_usage_for_multiprice_subscriptions');

        $subscription = $user->newSubscription('main', [static::$licensedPrice])
            ->meteredPrice(static::$meteredPrice)
            ->meteredPrice(static::$otherMeteredPrice)
            ->create('pm_card_visa');

        $this->assertSame($subscription->items->count(), 3);

        try {
            $subscription->reportUsage();
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);

            $this->assertSame(
                'This method requires a price argument since the subscription has multiple prices.', $e->getMessage()
            );
        }

        $subscription->reportUsageFor(static::$otherMeteredPrice, 20);

        $summary = $subscription->usageRecordsFor(static::$otherMeteredPrice)->first();

        $this->assertSame($summary->total_usage, 20);

        try {
            $subscription->reportUsageFor(static::$licensedPrice);
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidRequestException::class, $e);
        }
    }

    public function test_swap_metered_price_to_different_price()
    {
        $user = $this->createCustomer('swap_metered_price_to_different_price');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        $this->assertSame(static::$meteredPrice, $subscription->stripe_price);
        $this->assertNull($subscription->quantity);

        $subscription = $subscription->swap(static::$otherMeteredPrice);

        $this->assertSame(static::$otherMeteredPrice, $subscription->stripe_price);
        $this->assertNull($subscription->quantity);

        $subscription = $subscription->swap(static::$licensedPrice);

        $this->assertSame(static::$licensedPrice, $subscription->stripe_price);
        $this->assertSame(1, $subscription->quantity);
    }

    public function test_swap_metered_price_to_different_price_with_a_multiprice_subscription()
    {
        $user = $this->createCustomer('swap_metered_price_to_different_price_with_a_multiprice_subscription');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        $this->assertSame(static::$meteredPrice, $subscription->stripe_price);
        $this->assertNull($subscription->quantity);

        $subscription = $subscription->swap([static::$meteredPrice, static::$otherMeteredPrice]);

        $item = $subscription->findItemOrFail(self::$meteredPrice);
        $otherItem = $subscription->findItemOrFail(self::$otherMeteredPrice);

        $this->assertCount(2, $subscription->items);
        $this->assertNull($subscription->stripe_price);
        $this->assertNull($subscription->quantity);
        $this->assertSame(self::$meteredPrice, $item->stripe_price);
        $this->assertNull($item->quantity);
        $this->assertSame(self::$otherMeteredPrice, $otherItem->stripe_price);
        $this->assertNull($otherItem->quantity);

        $subscription = $subscription->swap(static::$otherMeteredPrice);

        $this->assertCount(1, $subscription->items);
        $this->assertSame(self::$otherMeteredPrice, $subscription->stripe_price);
        $this->assertNull($subscription->quantity);

        $subscription = $subscription->swap(static::$licensedPrice);

        $this->assertCount(1, $subscription->items);
        $this->assertSame(self::$licensedPrice, $subscription->stripe_price);
        $this->assertSame(1, $subscription->quantity);

        $subscription = $subscription->swap([static::$licensedPrice, static::$meteredPrice]);

        $this->assertCount(2, $subscription->items);
        $this->assertNull($subscription->stripe_price);
        $this->assertNull($subscription->quantity);
    }

    public function test_cancel_metered_subscription()
    {
        $user = $this->createCustomer('cancel_metered_subscription');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        $subscription->reportUsage(10);

        $subscription->cancel();

        $invoice = $user->upcomingInvoice();

        $this->assertEquals('$10.00', $invoice->total());
    }

    public function test_cancel_metered_subscription_immediately()
    {
        $user = $this->createCustomer('cancel_metered_subscription_immediately');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        $subscription->reportUsage(10);

        $subscription->cancelNowAndInvoice();

        $this->assertNull($user->upcomingInvoice());
        $this->assertCount(2, $invoices = $user->invoicesIncludingPending());
        $this->assertEquals('$10.00', $invoices->first()->total());
    }
}
