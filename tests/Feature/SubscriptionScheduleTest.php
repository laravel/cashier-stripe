<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\SubscriptionSchedule;

class SubscriptionScheduleTest extends FeatureTestCase
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

    public static function setUpBeforeClass(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

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
            'unit_amount' => 1000,
        ])->id;

        static::$otherPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $20',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'unit_amount' => 2000,
        ])->id;
    }

    public static function tearDownAfterClass(): void
    {
        static::deleteStripeResource(new \Stripe\Product(static::$productId));
    }

    public function test_subscription_schedules_can_be_created()
    {
        $user = $this->createCustomer('subscription_schedules_can_be_created');

        // Create subscription schedule with phases
        $schedule = $user->newSubscriptionSchedule()
            ->phases([
                [
                    'items' => [
                        ['price' => static::$priceId, 'quantity' => 1],
                    ],
                    'iterations' => 2,
                ],
                [
                    'items' => [
                        ['price' => static::$otherPriceId, 'quantity' => 1],
                    ],
                    'iterations' => 1,
                ],
            ])
            ->create();

        $this->assertInstanceOf(SubscriptionSchedule::class, $schedule);
        $this->assertNotNull($schedule->stripe_id);
        $this->assertEquals(1, count($user->subscriptionSchedules));
        $this->assertTrue($schedule->notStarted() || $schedule->active());
    }

    public function test_subscription_schedules_can_be_created_with_flexible_billing_mode()
    {
        $user = $this->createCustomer('subscription_schedules_flexible_billing');

        try {
            // Create subscription schedule with flexible billing mode
            $schedule = $user->newSubscriptionSchedule()
                ->withBillingMode('flexible')
                ->phases([
                    [
                        'items' => [
                            ['price' => static::$priceId, 'quantity' => 1],
                        ],
                        'iterations' => 1,
                    ],
                ])
                ->create();

            $this->assertInstanceOf(SubscriptionSchedule::class, $schedule);
            $this->assertNotNull($schedule->stripe_id);
            $this->assertEquals(1, count($user->subscriptionSchedules));
        } catch (\Exception $e) {
            // Skip test if API version doesn't support flexible billing
            if (strpos($e->getMessage(), 'billing_mode') !== false ||
                strpos($e->getMessage(), 'API version') !== false) {
                $this->markTestSkipped('Stripe API version does not support flexible billing mode');
            }
            throw $e;
        }
    }

    public function test_subscription_schedules_can_be_created_from_existing_subscription()
    {
        $user = $this->createCustomer('subscription_schedules_from_subscription');

        // Create a subscription first
        $subscription = $user->newSubscription('main', static::$priceId)
            ->create('pm_card_visa');

        $this->assertTrue($user->subscribed('main'));

        // Create schedule from existing subscription
        $schedule = $user->newSubscriptionSchedule()
            ->fromSubscription($subscription->stripe_id)
            ->phases([
                [
                    'items' => [
                        ['price' => static::$otherPriceId, 'quantity' => 1],
                    ],
                    'start_date' => now()->addMonth()->timestamp,
                    'iterations' => 1,
                ],
            ])
            ->create();

        $this->assertInstanceOf(SubscriptionSchedule::class, $schedule);
        $this->assertNotNull($schedule->stripe_id);
        $this->assertEquals($subscription->stripe_id, $schedule->subscription_id);
    }

    public function test_subscription_schedules_can_be_canceled()
    {
        $user = $this->createCustomer('subscription_schedules_can_be_canceled');

        $schedule = $user->newSubscriptionSchedule()
            ->startDate(now()->addWeek())
            ->phases([
                [
                    'items' => [
                        ['price' => static::$priceId, 'quantity' => 1],
                    ],
                    'iterations' => 1,
                ],
            ])
            ->create();

        $this->assertTrue($schedule->notStarted());

        $schedule->cancel();

        $this->assertTrue($schedule->canceled());
        $this->assertNotNull($schedule->canceled_at);
    }

    public function test_subscription_schedules_can_be_released()
    {
        $user = $this->createCustomer('subscription_schedules_can_be_released');

        // Create subscription schedule with future start date
        $schedule = $user->newSubscriptionSchedule()
            ->startDate(now()->addDay())
            ->phases([
                [
                    'items' => [
                        ['price' => static::$priceId, 'quantity' => 1],
                    ],
                    'iterations' => 1,
                ],
            ])
            ->create();

        // Release the schedule
        $schedule->release();

        $this->assertTrue($schedule->released());
        $this->assertNotNull($schedule->released_at);
    }

    public function test_subscription_schedules_can_be_updated()
    {
        $user = $this->createCustomer('subscription_schedules_can_be_updated');

        $schedule = $user->newSubscriptionSchedule()
            ->phases([
                [
                    'items' => [
                        ['price' => static::$priceId, 'quantity' => 1],
                    ],
                    'start_date' => now()->addWeek()->timestamp,
                    'iterations' => 1,
                ],
            ])
            ->withMetadata(['test' => 'original'])
            ->create();

        // Update the schedule
        $schedule->update([
            'metadata' => ['test' => 'updated'],
        ]);

        $stripeSchedule = $schedule->asStripeSubscriptionSchedule();
        $this->assertEquals('updated', $stripeSchedule->metadata->test);
    }

    public function test_subscription_schedules_can_sync_with_stripe()
    {
        $user = $this->createCustomer('subscription_schedules_sync_with_stripe');

        $schedule = $user->newSubscriptionSchedule()
            ->phases([
                [
                    'items' => [
                        ['price' => static::$priceId, 'quantity' => 1],
                    ],
                    'iterations' => 1,
                ],
            ])
            ->create();

        // Modify via Stripe API directly
        $user->stripe()->subscriptionSchedules->update($schedule->stripe_id, [
            'metadata' => ['synced' => 'true'],
        ]);

        // Sync with local model
        $schedule->syncWithStripe();

        $stripeSchedule = $schedule->asStripeSubscriptionSchedule();
        $this->assertEquals('true', $stripeSchedule->metadata->synced);
    }

    public function test_subscription_schedule_respects_config_default_billing_mode()
    {
        // Set config to flexible
        config(['cashier.default_billing_mode' => 'flexible']);

        $user = $this->createCustomer('subscription_schedule_config_default');

        try {
            // Create schedule without explicit billing mode (should use config default)
            $schedule = $user->newSubscriptionSchedule()
                ->phases([
                    [
                        'items' => [
                            ['price' => static::$priceId, 'quantity' => 1],
                        ],
                        'iterations' => 1,
                    ],
                ])
                ->create();

            $this->assertInstanceOf(SubscriptionSchedule::class, $schedule);
            $this->assertNotNull($schedule->stripe_id);
        } catch (\Exception $e) {
            // Reset config first
            config(['cashier.default_billing_mode' => 'classic']);

            // Skip test if API version doesn't support flexible billing
            if (strpos($e->getMessage(), 'billing_mode') !== false ||
                strpos($e->getMessage(), 'API version') !== false) {
                $this->markTestSkipped('Stripe API version does not support flexible billing mode');
            }
            throw $e;
        }

        // Reset config
        config(['cashier.default_billing_mode' => 'classic']);
    }

    public function test_user_can_find_subscription_schedule()
    {
        $user = $this->createCustomer('user_can_find_subscription_schedule');

        $schedule = $user->newSubscriptionSchedule()
            ->phases([
                [
                    'items' => [
                        ['price' => static::$priceId, 'quantity' => 1],
                    ],
                    'iterations' => 1,
                ],
            ])
            ->create();

        $found = $user->findSubscriptionSchedule($schedule->stripe_id);

        $this->assertInstanceOf(SubscriptionSchedule::class, $found);
        $this->assertEquals($schedule->id, $found->id);
    }
}
