<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Support\Str;
use InvalidArgumentException;

class MeteredBillingTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $meterId;

    /**
     * @var string
     */
    protected static $otherMeterId;

    /**
     * @var string
     */
    protected static $meterEventName;

    /**
     * @var string
     */
    protected static $otherMeterEventName;

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
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

        parent::setUpBeforeClass();

        static::$productId = self::stripe()->products->create([
            'name' => 'Laravel Cashier Test Product',
        ])->id;

        // Create meters for the new billing system with unique event names
        $timestamp = hrtime(true);
        $testerBuildId = getenv('CASHIER_TESTER_BUILD_ID') ?? 'default';

        static::$meterEventName = implode('_', ['api_request', $testerBuildId, $timestamp]);
        static::$otherMeterEventName = implode('_', ['premium_api_request', $testerBuildId, $timestamp]);

        static::$meterId = self::stripe()->billing->meters->create([
            'display_name' => 'API Requests',
            'event_name' => static::$meterEventName,
            'customer_mapping' => [
                'event_payload_key' => 'stripe_customer_id',
                'type' => 'by_id',
            ],
            'value_settings' => [
                'event_payload_key' => 'value',
            ],
            'default_aggregation' => [
                'formula' => 'sum',
            ],
        ])->id;

        static::$otherMeterId = self::stripe()->billing->meters->create([
            'display_name' => 'Premium API Requests',
            'event_name' => static::$otherMeterEventName,
            'customer_mapping' => [
                'event_payload_key' => 'stripe_customer_id',
                'type' => 'by_id',
            ],
            'value_settings' => [
                'event_payload_key' => 'value',
            ],
            'default_aggregation' => [
                'formula' => 'sum',
            ],
        ])->id;

        // Create metered prices with meters
        static::$meteredPrice = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly Metered $1 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'meter' => static::$meterId,
            ],
            'unit_amount' => 100,
        ])->id;

        static::$otherMeteredPrice = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly Metered $2 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'meter' => static::$otherMeterId,
            ],
            'unit_amount' => 200,
        ])->id;

        static::$licensedPrice = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10 Licensed',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'unit_amount' => 1000,
        ])->id;
    }

    public function test_report_usage_for_metered_price()
    {
        $this->markTestSkipped('Unable to use testmode key with `v2` API: https://docs.stripe.com/api-v2-overview?api-version=2025-07-30.preview&rds=1#limitations');

        $user = $this->createCustomer('report_usage_for_metered_price');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        $item = $subscription->items->first();
        $this->assertSame(static::$meterId, $item->meter_id);
        $this->assertSame(static::$meterEventName, $item->meter_event_name);

        // Test that meter events are created successfully with synchronous validation
        $event1 = $subscription->reportUsage(5);
        $this->assertNotNull($event1);
        $this->assertEquals('v2.billing.meter_event', $event1->object);
        $this->assertTrue(Str::isUuid($event1->identifier));

        $event2 = $subscription->reportUsageFor(static::$meteredPrice, 10);
        $this->assertNotNull($event2);
        $this->assertEquals('v2.billing.meter_event', $event2->object);
        $this->assertTrue(Str::isUuid($event2->identifier));

        // Verify the events have the correct payload values
        $this->assertEquals('5', $event1->payload['value']);
        $this->assertEquals('10', $event2->payload['value']);
        $this->assertEquals($user->stripeId(), $event1->payload['stripe_customer_id']);
        $this->assertEquals($user->stripeId(), $event2->payload['stripe_customer_id']);
    }

    public function test_reporting_usage_for_licensed_price_throws_exception()
    {
        $user = $this->createCustomer('reporting_usage_for_licensed_price_throws_exception');

        $subscription = $user->newSubscription('main', static::$licensedPrice)->create('pm_card_visa');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must have a meter to report usage. Legacy usage records are no longer supported.');

        $subscription->reportUsage();
    }

    public function test_reporting_usage_for_subscriptions_with_multiple_prices()
    {
        $user = $this->createCustomer('reporting_usage_for_subscriptions_with_multiple_prices');

        $subscription = $user->newSubscription('main', [static::$licensedPrice])
            ->meteredPrice(static::$meteredPrice)
            ->meteredPrice(static::$otherMeteredPrice)
            ->create('pm_card_visa');

        $this->assertSame($subscription->items->count(), 3);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This method requires a price argument since the subscription has multiple prices.');

        $subscription->reportUsage();
    }

    public function test_reporting_usage_for_specific_metered_price()
    {
        $this->markTestSkipped('Unable to use testmode key with `v2` API: https://docs.stripe.com/api-v2-overview?api-version=2025-07-30.preview&rds=1#limitations');

        $user = $this->createCustomer('reporting_usage_for_specific_metered_price');

        $subscription = $user->newSubscription('main', [static::$licensedPrice])
            ->meteredPrice(static::$meteredPrice)
            ->meteredPrice(static::$otherMeteredPrice)
            ->create('pm_card_visa');

        // Test that meter event is created successfully with synchronous validation
        $event = $subscription->reportUsageFor(static::$otherMeteredPrice, 20);
        $this->assertNotNull($event);
        $this->assertEquals('v2.billing.meter_event', $event->object);
        $this->assertTrue(Str::isUuid($event->identifier));
        $this->assertEquals('20', $event->payload['value']);
        $this->assertEquals($user->stripeId(), $event->payload['stripe_customer_id']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must have a meter to report usage. Legacy usage records are no longer supported.');

        $subscription->reportUsageFor(static::$licensedPrice);
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

    public function test_swap_metered_price_to_different_price_with_a_subscription_with_multiple_prices()
    {
        $user = $this->createCustomer('swap_metered_price_to_different_price_with_a_subscription_with_multiple_prices');

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

    public function test_add_metered_price_to_a_subscription_with_multiple_prices()
    {
        $user = $this->createCustomer('add_metered_price_to_a_subscription_with_multiple_prices');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        $this->assertSame(static::$meteredPrice, $subscription->stripe_price);
        $this->assertNull($subscription->quantity);

        $subscription = $subscription->addMeteredPrice(static::$otherMeteredPrice);

        $subscription->findItemOrFail(self::$meteredPrice);
        $subscription->findItemOrFail(self::$otherMeteredPrice);

        $this->assertCount(2, $subscription->items);
        $this->assertNull($subscription->stripe_price);
        $this->assertNull($subscription->quantity);
    }

    public function test_cancel_metered_subscription()
    {
        $this->markTestSkipped('Unable to use testmode key with `v2` API: https://docs.stripe.com/api-v2-overview?api-version=2025-07-30.preview&rds=1#limitations');

        $user = $this->createCustomer('cancel_metered_subscription');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        // Test that meter event is created successfully with synchronous validation
        $event = $subscription->reportUsage(10);
        $this->assertNotNull($event);
        $this->assertEquals('v2.billing.meter_event', $event->object);
        $this->assertTrue(Str::isUuid($event->identifier));
        $this->assertEquals('10', $event->payload['value']);

        $subscription->cancel();

        // Verify the subscription is properly canceled
        $this->assertTrue($subscription->canceled());

        // Verify that an upcoming invoice exists (even if usage hasn't been processed yet)
        $invoice = $user->upcomingInvoice();
        $this->assertNotNull($invoice);
        $this->assertInstanceOf(\Laravel\Cashier\Invoice::class, $invoice);
    }

    public function test_cancel_metered_subscription_immediately()
    {
        $this->markTestSkipped('Unable to use testmode key with `v2` API: https://docs.stripe.com/api-v2-overview?api-version=2025-07-30.preview&rds=1#limitations');

        $user = $this->createCustomer('cancel_metered_subscription_immediately');

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$meteredPrice)
            ->create('pm_card_visa');

        // Test that meter event is created successfully with synchronous validation
        $event = $subscription->reportUsage(10);
        $this->assertNotNull($event);
        $this->assertEquals('v2.billing.meter_event', $event->object);
        $this->assertTrue(Str::isUuid($event->identifier));
        $this->assertEquals('10', $event->payload['value']);

        $subscription->cancelNowAndInvoice();

        $this->assertNull($user->upcomingInvoice());
        $invoices = $user->invoicesIncludingPending();

        // There should be at least one invoice
        $this->assertGreaterThanOrEqual(1, $invoices->count());

        // Verify the subscription was properly canceled
        $this->assertTrue($subscription->canceled());
    }
}
