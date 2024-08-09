<?php

namespace Laravel\Cashier\Tests\Feature;

use Exception;
use InvalidArgumentException;

class UsageBasedBillingTest extends FeatureTestCase
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
    protected static $meteredEventPrice;

    /**
     * @var string
     */
    protected static $otherMeteredEventPrice;

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
    protected static $licensedPrice;

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

        self::$meterEventName = 'test-meter-1';
        self::$otherMeterEventName = 'test-meter-2';

        $meters = self::stripe()->billing->meters->all();

        foreach ($meters as $meter) {
            if ($meter->event_name === self::$meterEventName && $meter->status === 'active') {
                self::stripe()->billing->meters->deactivate($meter->id);
            }
            if ($meter->event_name === self::$otherMeterEventName && $meter->status === 'active') {
                self::stripe()->billing->meters->deactivate($meter->id);
            }
        }

        static::$meterId = self::stripe()->billing->meters->create([
            'display_name' => 'example meter 1',
            'event_name' => self::$meterEventName,
            'default_aggregation' => ['formula' => 'sum'],
            'customer_mapping' => [
                'type' => 'by_id',
                'event_payload_key' => 'stripe_customer_id',
            ],
        ])->id;

        static::$otherMeterId = self::stripe()->billing->meters->create([
            'display_name' => 'example meter 2',
            'event_name' => self::$otherMeterEventName,
            'default_aggregation' => ['formula' => 'sum'],
            'customer_mapping' => [
                'type' => 'by_id',
                'event_payload_key' => 'stripe_customer_id',
            ],
        ])->id;

        static::$meteredEventPrice = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly Metered Event $1 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'meter' => static::$meterId,
            ],
            'billing_scheme' => 'per_unit',
            'unit_amount' => 100,
        ])->id;

        static::$otherMeteredEventPrice = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly Metered Event $2 per unit',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'meter' => static::$otherMeterId,
            ],
            'billing_scheme' => 'per_unit',
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

    public function test_report_usage_for_meter()
    {
        $user = $this->createCustomer('test_report_usage_for_meter');

        $user->newSubscription('main')
            ->meteredPrice(static::$meteredEventPrice)
            ->create('pm_card_visa');

        sleep(1);

        $user->reportMeterEvent(static::$meterEventName, 10);

        $summary = $user->meterEventSummaries(static::$meterId)->first();

        $this->assertSame($summary->aggregated_value, 10.0);
    }

    public function test_reporting_event_usage_for_subscriptions_with_multiple_prices()
    {
        $user = $this->createCustomer('reporting_usage_for_subscriptions_with_multiple_prices');

        $subscription = $user->newSubscription('main', [static::$licensedPrice])
            ->meteredPrice(static::$meteredEventPrice)
            ->meteredPrice(static::$otherMeteredEventPrice)
            ->create('pm_card_visa');

        $this->assertSame($subscription->items->count(), 3);

        try {
            $user->reportMeterEvent(static::$meterEventName);
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);

            $this->assertSame(
                'This method requires a price argument since the subscription has multiple prices.', $e->getMessage()
            );
        }

        $user->reportMeterEvent(static::$otherMeterEventName, 20);

        try {
            $user->meterEventSummaries(static::$otherMeterId)->first();
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);

            $this->assertSame(
                'This method requires a price argument since the subscription has multiple prices.', $e->getMessage()
            );
        }

        $summary = $user->meterEventSummaries(static::$otherMeterId)->first();

        $this->assertSame($summary->aggregated_value, 20.0);
    }
}
